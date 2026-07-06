<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CommTemplate;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use App\Services\Integrations\IngestOfficeMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matter $matter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->matter = Matter::factory()->create([
            'client_id' => Client::factory()->create()->id,
        ]);
    }

    private function upload(array $overrides = []): Document
    {
        $this->actingAs($this->user)->post(route('matters.documents.store', $this->matter), array_merge([
            'file' => UploadedFile::fake()->create('spec.pdf', 120, 'application/pdf'),
            'title' => '',
            'category' => 'filed_document',
        ], $overrides));

        return Document::latest('id')->first();
    }

    public function test_a_document_is_uploaded_and_stored_on_the_matter(): void
    {
        $document = $this->upload();

        $this->assertSame('spec', $document->title); // defaults to the file name
        $this->assertSame('filed_document', $document->category->value);
        $this->assertSame('upload', $document->source);
        $this->assertSame($this->user->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_documents_download_with_their_original_filename(): void
    {
        $document = $this->upload(['title' => 'Specification']);

        $this->actingAs($this->user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('spec.pdf');
    }

    public function test_replacing_a_document_creates_a_new_version_and_keeps_the_old(): void
    {
        $document = $this->upload();

        $this->actingAs($this->user)->post(route('documents.replace', $document), [
            'file' => UploadedFile::fake()->create('spec-v2.pdf', 80, 'application/pdf'),
        ])->assertSessionHas('success');

        $replacement = Document::latest('id')->first();
        $this->assertSame(2, $replacement->version);
        $this->assertSame($document->id, $replacement->parent_id);
        $this->assertSame($document->title, $replacement->title);

        // The panel lists only the current version; the old bytes remain
        $current = Document::query()->where('matter_id', $this->matter->id)->current()->get();
        $this->assertSame([$replacement->id], $current->pluck('id')->all());
        Storage::disk('local')->assertExists($document->fresh()->path);

        // A superseded version can't be replaced again
        $this->actingAs($this->user)->post(route('documents.replace', $document), [
            'file' => UploadedFile::fake()->create('spec-v3.pdf', 10),
        ])->assertSessionHas('error');
    }

    public function test_a_pdf_is_generated_from_a_template_with_merge_fields_resolved(): void
    {
        $template = CommTemplate::create([
            'name' => 'Reporting letter', 'channel' => 'letter',
            'subject' => 'Our ref {{matter.reference}}',
            'body' => 'We report on {{matter.title}} filed as {{matter.application_no}}.',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('matters.documents.generate', $this->matter), [
                'comm_template_id' => $template->id,
            ])
            ->assertSessionHas('success');

        $document = Document::latest('id')->first();
        $this->assertSame('generated', $document->source);
        $this->assertSame('generated', $document->category->value);
        $this->assertSame("Our ref {$this->matter->reference}", $document->title);
        $this->assertSame('application/pdf', $document->mime);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->path));
    }

    public function test_office_messages_auto_file_their_documents_on_the_matter(): void
    {
        $this->matter->update(['application_no' => 'EP21789012.3']);

        app(IngestOfficeMessages::class)->ingest('epo', [[
            'external_id' => 'EPO-DOC-1',
            'event_type' => 'office_action',
            'application_no' => 'EP21789012.3',
            'payload' => [
                'documents' => [[
                    'name' => 'communication.pdf',
                    'title' => 'Communication under Art. 94(3)',
                    'category' => 'office_action',
                    'content_base64' => base64_encode('%PDF-1.4 examination communication'),
                ]],
            ],
        ]]);

        $document = $this->matter->documents()->first();
        $this->assertNotNull($document);
        $this->assertSame('Communication under Art. 94(3)', $document->title);
        $this->assertSame('office', $document->source);
        $this->assertSame('office_action', $document->category->value);
        $this->assertSame('%PDF-1.4 examination communication', Storage::disk('local')->get($document->path));

        $message = \App\Models\OfficeMessage::firstWhere('external_id', 'EPO-DOC-1');
        $this->assertContains('Filed document “Communication under Art. 94(3)” from the office message', $message->actions);
    }

    public function test_documents_can_be_renamed_and_deleted(): void
    {
        $document = $this->upload();

        $this->actingAs($this->user)->patch(route('documents.update', $document), [
            'title' => 'Specification (clean)', 'category' => 'evidence',
        ])->assertSessionHas('success');
        $this->assertSame('Specification (clean)', $document->fresh()->title);

        $path = $document->path;
        $this->actingAs($this->user)
            ->delete(route('documents.destroy', $document))
            ->assertSessionHas('success');
        $this->assertNull(Document::find($document->id));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_the_matter_page_lists_current_documents(): void
    {
        $this->upload(['title' => 'Spec']);

        $this->actingAs($this->user)
            ->get(route('matters.show', $this->matter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('documents', 1)
                ->where('documents.0.title', 'Spec')
                ->where('documents.0.source', 'upload')
                ->has('documentCategories'));
    }
}
