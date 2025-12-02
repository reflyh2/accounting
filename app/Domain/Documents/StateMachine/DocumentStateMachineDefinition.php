<?php

namespace App\Domain\Documents\StateMachine;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class DocumentStateMachineDefinition
{
    /** @var array<string, array<string, DocumentStateTransition>> */
    private array $transitions = [];

    private ?Closure $beforeAny = null;
    private ?Closure $afterAny = null;

    public function __construct(
        private readonly string $enumClass,
        private readonly string $column = 'status',
    ) {
    }

    public static function make(string $enumClass, string $column = 'status'): self
    {
        return new self($enumClass, $column);
    }

    public function allow(DocumentStateTransition $transition): self
    {
        $this->transitions[$transition->from][$transition->to] = $transition;

        return $this;
    }

    public function column(): string
    {
        return $this->column;
    }

    public function enumClass(): string
    {
        return $this->enumClass;
    }

    /**
     * @return array<string, DocumentStateTransition>
     */
    public function transitionsFrom(BackedEnum|string $state): array
    {
        $key = $this->normalizeState($state);

        return $this->transitions[$key] ?? [];
    }

    public function findTransition(BackedEnum|string $from, BackedEnum|string $to): ?DocumentStateTransition
    {
        $fromKey = $this->normalizeState($from);
        $toKey = $this->normalizeState($to);

        return $this->transitions[$fromKey][$toKey] ?? null;
    }

    public function beforeAny(?Closure $callback): self
    {
        $this->beforeAny = $callback;

        return $this;
    }

    public function afterAny(?Closure $callback): self
    {
        $this->afterAny = $callback;

        return $this;
    }

    public function runBeforeAny(Model $model, ?Authenticatable $actor, array $context = []): void
    {
        if ($this->beforeAny) {
            ($this->beforeAny)($model, $actor, $context);
        }
    }

    public function runAfterAny(Model $model, ?Authenticatable $actor, array $context = []): void
    {
        if ($this->afterAny) {
            ($this->afterAny)($model, $actor, $context);
        }
    }

    public function asEnum(BackedEnum|string $value): BackedEnum
    {
        $enumClass = $this->enumClass;

        if ($value instanceof BackedEnum) {
            return $value;
        }

        return $enumClass::from($value);
    }

    private function normalizeState(BackedEnum|string $state): string
    {
        return $state instanceof BackedEnum ? $state->value : (string) $state;
    }
}


