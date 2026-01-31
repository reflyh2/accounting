<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\SalesOrderStatus;

final class SalesOrderStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(SalesOrderStatus::class)
            ->allow(DocumentStateTransition::make(SalesOrderStatus::DRAFT, SalesOrderStatus::QUOTE))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::DRAFT, SalesOrderStatus::CONFIRMED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::QUOTE, SalesOrderStatus::CONFIRMED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::CONFIRMED, SalesOrderStatus::PARTIALLY_DELIVERED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::CONFIRMED, SalesOrderStatus::DELIVERED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::PARTIALLY_DELIVERED, SalesOrderStatus::DELIVERED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::DELIVERED, SalesOrderStatus::CLOSED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::PARTIALLY_DELIVERED, SalesOrderStatus::CLOSED))
            // Reverse transitions (when deliveries are deleted)
            ->allow(DocumentStateTransition::make(SalesOrderStatus::DELIVERED, SalesOrderStatus::PARTIALLY_DELIVERED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::DELIVERED, SalesOrderStatus::CONFIRMED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::PARTIALLY_DELIVERED, SalesOrderStatus::CONFIRMED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::DRAFT, SalesOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::QUOTE, SalesOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(SalesOrderStatus::CONFIRMED, SalesOrderStatus::CANCELED));
    }
}
