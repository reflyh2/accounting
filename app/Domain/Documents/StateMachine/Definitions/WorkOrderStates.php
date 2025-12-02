<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\WorkOrderStatus;

final class WorkOrderStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(WorkOrderStatus::class)
            ->allow(DocumentStateTransition::make(WorkOrderStatus::DRAFT, WorkOrderStatus::RELEASED))
            ->allow(DocumentStateTransition::make(WorkOrderStatus::RELEASED, WorkOrderStatus::IN_PROGRESS))
            ->allow(DocumentStateTransition::make(WorkOrderStatus::IN_PROGRESS, WorkOrderStatus::COMPLETED))
            ->allow(DocumentStateTransition::make(WorkOrderStatus::RELEASED, WorkOrderStatus::COMPLETED))
            ->allow(DocumentStateTransition::make(WorkOrderStatus::COMPLETED, WorkOrderStatus::CLOSED))
            ->allow(DocumentStateTransition::make(WorkOrderStatus::DRAFT, WorkOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(WorkOrderStatus::RELEASED, WorkOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(WorkOrderStatus::IN_PROGRESS, WorkOrderStatus::CANCELED));
    }
}


