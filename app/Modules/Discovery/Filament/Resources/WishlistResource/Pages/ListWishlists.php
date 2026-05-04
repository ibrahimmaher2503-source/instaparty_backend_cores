<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources\WishlistResource\Pages;

use App\Modules\Discovery\Filament\Resources\WishlistResource;
use Filament\Resources\Pages\ListRecords;

class ListWishlists extends ListRecords
{
    protected static string $resource = WishlistResource::class;
}
