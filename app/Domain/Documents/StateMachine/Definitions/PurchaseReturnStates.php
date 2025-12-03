<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\PurchaseReturnStatus;

final class PurchaseReturnStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(PurchaseReturnStatus::class)
            ->allow(DocumentStateTransition::make(PurchaseReturnStatus::DRAFT, PurchaseReturnStatus::POSTED));
    }
}


