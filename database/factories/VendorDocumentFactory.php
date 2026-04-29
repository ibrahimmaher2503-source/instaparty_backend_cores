<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorDocument>
 */
class VendorDocumentFactory extends Factory
{
    protected $model = VendorDocument::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => VendorProfile::factory(),
            'doc_type' => fake()->randomElement(DocumentType::cases())->value,
            'file_path' => 'documents/'.Str::ulid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'status' => DocumentStatus::Pending->value,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'review_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved->value,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected->value,
            'reviewed_at' => now(),
            'review_notes' => ['en' => 'Document rejected.', 'ar' => 'تم رفض المستند.'],
        ]);
    }
}
