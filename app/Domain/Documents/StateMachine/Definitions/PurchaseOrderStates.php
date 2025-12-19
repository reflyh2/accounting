<?php

namespace App\Domain\Documents\StateMachine\Definitions;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\DocumentStateException;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class PurchaseOrderStates
{
    public static function definition(): DocumentStateMachineDefinition
    {
        return DocumentStateMachineDefinition::make(PurchaseOrderStatus::class)
            ->allow(
                DocumentStateTransition::make(PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::APPROVED)
                    ->guard(self::makerCheckerGuard())
            )
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::SENT))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::SENT, PurchaseOrderStatus::PARTIALLY_RECEIVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::SENT, PurchaseOrderStatus::RECEIVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::RECEIVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::CLOSED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::PARTIALLY_RECEIVED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::CLOSED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::CANCELED))
            ->allow(DocumentStateTransition::make(PurchaseOrderStatus::SENT, PurchaseOrderStatus::CANCELED));
    }

    private static function makerCheckerGuard(): Closure
    {
        return static function (
            Model $document,
            ?Authenticatable $actor,
            array $context
        ): bool {
            $shouldEnforce = $context['enforceMakerChecker']
                ?? config('purchasing.maker_checker.enforce', false);

            if (!$shouldEnforce) {
                return true;
            }

            if (!$actor) {
                throw new DocumentStateException('Dokumen membutuhkan checker yang sah untuk persetujuan.');
            }

            if ($document->created_by !== null
                && (string) $document->created_by === (string) $actor->getAuthIdentifier()
            ) {
                throw new DocumentStateException('Pembuat dokumen tidak boleh menyetujui PO yang sama.');
            }

            return true;
        };
    }
}


