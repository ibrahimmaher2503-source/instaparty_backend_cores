<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\MarkServicePendingReviewForMaterialEditAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditRentalService extends EditRecord
{
    use Translatable;

    protected static string $resource = RentalServiceResource::class;

    private ?string $serviceModerationMediaSignature = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->serviceModerationMediaSignature = $this->gallerySignature();
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if (! $record instanceof Service) {
            return;
        }

        app(MarkServicePendingReviewForMaterialEditAction::class)->execute(
            $record,
            coreMediaChanged: $this->serviceModerationMediaSignature !== $this->gallerySignature(),
        );
    }

    private function gallerySignature(): string
    {
        $record = $this->record;

        if (! $record instanceof Service) {
            return '';
        }

        return $record->getMedia('gallery')
            ->map(fn ($media): string => $media->id.':'.$media->updated_at?->getTimestamp())
            ->implode('|');
    }
}
