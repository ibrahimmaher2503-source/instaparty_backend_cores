<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum ModificationProposalKind: string
{
    case AddItem = 'add_item';
    case RemoveItem = 'remove_item';
    case ChangeQuantity = 'change_quantity';
    case ChangePrice = 'change_price';
    case ChangeSlot = 'change_slot';
    case AddSurcharge = 'add_surcharge';
    case AddNote = 'add_note';
}
