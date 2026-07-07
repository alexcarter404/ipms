<?php

namespace Database\Factories;

use App\Models\CommTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommTemplate>
 */
class CommTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->words(3, true)),
            'channel' => 'email',
            'matter_type' => null,
            'subject' => '{{matter.reference}} — Update',
            'body' => "Dear {{contact.name}},\n\nRe: {{matter.title}} ({{matter.reference}})\n\nKind regards,\n{{attorney.name}}",
            'is_active' => true,
        ];
    }
}
