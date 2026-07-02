<?php

namespace App\Services;

use App\Models\CommTemplate;
use App\Models\Matter;

/**
 * Renders {{merge.field}} placeholders in communication templates
 * against a matter and its related records.
 */
class TemplateRenderer
{
    /**
     * @return array{subject: string, body: string}
     */
    public function render(CommTemplate $template, Matter $matter): array
    {
        $fields = $this->fields($matter);

        return [
            'subject' => $this->interpolate($template->subject ?? '', $fields),
            'body' => $this->interpolate($template->body, $fields),
        ];
    }

    /**
     * All merge fields available to templates, with their current values.
     *
     * @return array<string, string>
     */
    public function fields(Matter $matter): array
    {
        $matter->loadMissing(['client', 'billingEntity', 'contacts', 'responsibleUser']);

        $fmt = fn ($date) => $date?->format('j F Y') ?? '';
        $entity = $matter->effectiveBillingEntity();
        $contact = $matter->mainContact();
        $docketing = $matter->docketingContact();

        return [
            'matter.reference' => $matter->reference,
            'matter.title' => $matter->title,
            'matter.type' => $matter->matter_type->label(),
            'matter.status' => $matter->status->label(),
            'matter.country' => $matter->country_code,
            'matter.application_no' => $matter->application_no ?? '',
            'matter.application_date' => $fmt($matter->application_date),
            'matter.publication_no' => $matter->publication_no ?? '',
            'matter.publication_date' => $fmt($matter->publication_date),
            'matter.registration_no' => $matter->registration_no ?? '',
            'matter.registration_date' => $fmt($matter->registration_date),
            'matter.priority_date' => $fmt($matter->priority_date),
            'matter.expiry_date' => $fmt($matter->expiry_date),
            'matter.next_renewal_date' => $fmt($matter->renewals()->open()->orderBy('due_date')->first()?->due_date),
            'client.name' => $matter->client->name ?? '',
            'client.code' => $matter->client->code ?? '',
            'entity.name' => $entity->name ?? '',
            'entity.vat_number' => $entity->vat_number ?? '',
            'entity.billing_contact' => $entity->billing_contact_name ?? '',
            'entity.billing_email' => $entity->billing_email ?? '',
            'entity.billing_address' => $entity?->effectiveBillingAddress() ?? '',
            'entity.billing_reference' => $entity->billing_reference ?? '',
            'contact.name' => $contact->name ?? '',
            'contact.email' => $contact->email ?? '',
            'docketing.name' => $docketing->name ?? '',
            'docketing.email' => $docketing->email ?? '',
            'attorney.name' => $matter->responsibleUser->name ?? '',
            'attorney.email' => $matter->responsibleUser->email ?? '',
            'today' => now()->format('j F Y'),
        ];
    }

    /**
     * Merge field names for the template editor's placeholder helper.
     *
     * @return list<string>
     */
    public static function availableFields(): array
    {
        return [
            'matter.reference', 'matter.title', 'matter.type', 'matter.status',
            'matter.country', 'matter.application_no', 'matter.application_date',
            'matter.publication_no', 'matter.publication_date',
            'matter.registration_no', 'matter.registration_date',
            'matter.priority_date', 'matter.expiry_date', 'matter.next_renewal_date',
            'client.name', 'client.code',
            'entity.name', 'entity.vat_number', 'entity.billing_contact',
            'entity.billing_email', 'entity.billing_address', 'entity.billing_reference',
            'contact.name', 'contact.email', 'docketing.name', 'docketing.email',
            'attorney.name', 'attorney.email', 'today',
        ];
    }

    private function interpolate(string $text, array $fields): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_.]+)\s*\}\}/i',
            fn ($m) => $fields[strtolower($m[1])] ?? $m[0],
            $text
        );
    }
}
