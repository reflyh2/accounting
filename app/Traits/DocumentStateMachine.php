<?php

namespace App\Traits;

use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Domain\Documents\StateMachine\DocumentStateTransition;
use App\Events\Documents\DocumentStatusChanged;
use App\Exceptions\DocumentStateException;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Model
 */
trait DocumentStateMachine
{
    abstract protected static function stateMachineDefinition(): DocumentStateMachineDefinition;

    public function currentDocumentStatus(): BackedEnum
    {
        $definition = static::stateMachineDefinition();
        $column = $definition->column();
        $value = $this->getAttribute($column);

        if ($value === null) {
            throw new DocumentStateException(sprintf(
                'Document [%s] does not have a value for column [%s].',
                static::class,
                $column
            ));
        }

        return $definition->asEnum($value);
    }

    public function transitionTo(BackedEnum|string $next, ?Authenticatable $actor = null, array $context = []): static
    {
        $definition = static::stateMachineDefinition();
        $current = $this->currentDocumentStatus();
        $target = $definition->asEnum($next);

        if ($current->value === $target->value) {
            return $this;
        }

        $transition = $definition->findTransition($current, $target);

        if (!$transition) {
            throw new DocumentStateException(sprintf(
                'Transition from [%s] to [%s] is not allowed for [%s].',
                $current->value,
                $target->value,
                static::class
            ));
        }

        $actor ??= Auth::user();

        $this->assertAuthorized($transition, $actor, $context, $current, $target);

        DB::transaction(function () use ($definition, $transition, $actor, $context, $current, $target): void {
            $definition->runBeforeAny($this, $actor, $context);

            if ($transition->before) {
                ($transition->before)($this, $actor, $context, $current, $target);
            }

            $this->setAttribute($definition->column(), $target->value);
            $this->save();

            // Log the status change to audit trail
            $this->logStatusTransition($current, $target, $actor);

            $definition->runAfterAny($this, $actor, $context);

            if ($transition->after) {
                ($transition->after)($this, $actor, $context, $current, $target);
            }

            $eventClass = $transition->eventClass ?? DocumentStatusChanged::class;
            /** @var class-string $eventClass */
            event(new $eventClass($this->fresh(), $current, $target, $actor, $context));
        });

        return $this->refresh();
    }

    public function canTransitionTo(BackedEnum|string $next, ?Authenticatable $actor = null, array $context = []): bool
    {
        $definition = static::stateMachineDefinition();
        $current = $this->currentDocumentStatus();
        $target = $definition->asEnum($next);
        $transition = $definition->findTransition($current, $target);

        if (!$transition) {
            return false;
        }

        $actor ??= Auth::user();

        try {
            $this->assertAuthorized($transition, $actor, $context, $current, $target);
        } catch (AuthorizationException|DocumentStateException) {
            return false;
        }

        return true;
    }

    /**
     * @return BackedEnum[]
     */
    public function allowedDocumentStatuses(?Authenticatable $actor = null, array $context = []): array
    {
        $definition = static::stateMachineDefinition();
        $current = $this->currentDocumentStatus();
        $actor ??= Auth::user();

        $transitions = $definition->transitionsFrom($current);
        $allowed = [];

        foreach ($transitions as $transition) {
            $target = $definition->asEnum($transition->to);

            try {
                $this->assertAuthorized($transition, $actor, $context, $current, $target);
                $allowed[] = $target;
            } catch (AuthorizationException|DocumentStateException) {
                continue;
            }
        }

        return $allowed;
    }

    private function assertAuthorized(
        DocumentStateTransition $transition,
        ?Authenticatable $actor,
        array $context,
        BackedEnum $current,
        BackedEnum $target,
    ): void {
        if ($transition->ability) {
            Gate::authorize($transition->ability, [$this, $actor, $context, $current, $target]);
        }

        if ($transition->guard) {
            $result = ($transition->guard)($this, $actor, $context, $current, $target);

            if ($result === false) {
                throw new DocumentStateException('Transition blocked by guard.');
            }
        }
    }

    /**
     * Log a status transition to the audit trail.
     */
    private function logStatusTransition(BackedEnum $from, BackedEnum $to, ?Authenticatable $actor): void
    {
        if (!class_exists(\App\Models\AuditLog::class)) {
            return;
        }

        \App\Models\AuditLog::log(
            \App\Models\AuditLog::ACTION_STATUS_CHANGED,
            $this,
            ['status' => $from->value],
            ['status' => $to->value],
            ['status'],
            sprintf('Status changed from %s to %s', $from->value, $to->value),
            $actor?->global_id ?? $actor?->getAuthIdentifier(),
        );
    }
}


