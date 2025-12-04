<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\SalesReturnStatus;

final class SalesReturnStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(SalesReturnStatus::class)
            ->allow(DocumentStateTransition::make(SalesReturnStatus::DRAFT, SalesReturnStatus::POSTED));
    }
}
