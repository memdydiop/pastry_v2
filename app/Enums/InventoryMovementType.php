<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case IN = 'in';
    case OUT = 'out';
    case ADJUST = 'adjust';
    case LOSS = 'loss';
}
