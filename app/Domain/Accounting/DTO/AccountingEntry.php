<?php

namespace App\Domain\Accounting\DTO;

use App\Exceptions\AccountingEventException;
use Illuminate\Contracts\Support\Arrayable;

final class AccountingEntry implements Arrayable
{
    private const SCALE = 6;

    public function __construct(
        public readonly string $role,
        public readonly string $direction,
        public readonly float $amount,
        public readonly array $meta = [],
    ) {
        if (!in_array($this->direction, ['debit', 'credit'], true)) {
            throw new AccountingEventException("Invalid entry direction [{$this->direction}].");
        }

        if ($this->amount <= 0) {
            throw new AccountingEventException('Entry amount must be greater than zero.');
        }
    }

    public static function debit(string $role, float $amount, array $meta = []): self
    {
        return new self($role, 'debit', $amount, $meta);
    }

    public static function credit(string $role, float $amount, array $meta = []): self
    {
        return new self($role, 'credit', $amount, $meta);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'direction' => $this->direction,
            'amount' => $this->normalizedAmount(),
            'meta' => $this->meta,
        ];
    }

    private function normalizedAmount(): string
    {
        return number_format($this->amount, self::SCALE, '.', '');
    }
}


