<?php

namespace App\Domain\Documents\StateMachine;

use BackedEnum;
use Closure;

final class DocumentStateTransition
{
    public readonly string $from;
    public readonly string $to;
    public ?string $ability = null;
    public ?Closure $guard = null;
    public ?Closure $before = null;
    public ?Closure $after = null;
    public ?string $eventClass = null;

    private function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public static function make(BackedEnum|string $from, BackedEnum|string $to): self
    {
        return new self(
            from: self::value($from),
            to: self::value($to),
        );
    }

    public function ability(?string $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    public function guard(?Closure $guard): self
    {
        $this->guard = $guard;

        return $this;
    }

    public function before(?Closure $callback): self
    {
        $this->before = $callback;

        return $this;
    }

    public function after(?Closure $callback): self
    {
        $this->after = $callback;

        return $this;
    }

    public function event(?string $eventClass): self
    {
        $this->eventClass = $eventClass;

        return $this;
    }

    private static function value(BackedEnum|string $state): string
    {
        return $state instanceof BackedEnum ? $state->value : (string) $state;
    }
}


