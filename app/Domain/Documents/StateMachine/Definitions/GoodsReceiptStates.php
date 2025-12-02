<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\GoodsReceiptStatus;

final class GoodsReceiptStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(GoodsReceiptStatus::class)
            ->allow(DocumentStateTransition::make(GoodsReceiptStatus::DRAFT, GoodsReceiptStatus::POSTED));
    }
}


