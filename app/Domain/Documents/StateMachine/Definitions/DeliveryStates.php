<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\DeliveryStatus;

final class DeliveryStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(DeliveryStatus::class)
            ->allow(DocumentStateTransition::make(DeliveryStatus::DRAFT, DeliveryStatus::POSTED));
    }
}


