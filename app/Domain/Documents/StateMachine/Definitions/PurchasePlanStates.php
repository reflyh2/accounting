<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\PurchasePlanStatus;

final class PurchasePlanStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(PurchasePlanStatus::class)
            ->allow(DocumentStateTransition::make(PurchasePlanStatus::DRAFT, PurchasePlanStatus::CONFIRMED))
            ->allow(DocumentStateTransition::make(PurchasePlanStatus::CONFIRMED, PurchasePlanStatus::CLOSED))
            ->allow(DocumentStateTransition::make(PurchasePlanStatus::DRAFT, PurchasePlanStatus::CANCELLED))
            ->allow(DocumentStateTransition::make(PurchasePlanStatus::CONFIRMED, PurchasePlanStatus::CANCELLED));
    }
}
