<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;

final readonly class VendorModifyDTO
{
    /**
     * @param  array<int,array{change_kind:string,target_item_public_id?:string,payload:array<string,mixed>}>  $changes
     * @param  array<string,string>|null  $vendorExplanation
     */
    public function __construct(
        public int $bookingVendorId,
        public int $vendorProfileId,
        public int $proposedByUserId,
        public ModificationProposalKind $proposalKind,
        public array $changes,
        public ?array $vendorExplanation,
        public ?string $idempotencyKey = null,
        public ?string $previewToken = null,
    ) {}
}
