<?php

namespace App\Domain\Accounting\DTO;

use App\Enums\AccountingEventCode;
use App\Exceptions\AccountingEventException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;

final class AccountingEventPayload implements Arrayable
{
    private const BALANCE_TOLERANCE = 0.0001;

    /**
     * @var AccountingEntry[]
     */
    private array $lines = [];

    public function __construct(
        public readonly AccountingEventCode $code,
        public readonly int $companyId,
        public readonly ?int $branchId,
        public readonly string $documentType,
        public readonly int|string|null $documentId,
        public readonly ?string $documentNumber,
        public readonly string $currencyCode,
        public readonly float $exchangeRate,
        public readonly CarbonInterface $occurredAt,
        public readonly ?int $actorId = null,
        public readonly array $meta = [],
    ) {
    }

    /**
     * @param AccountingEntry[] $lines
     */
    public function setLines(array $lines): self
    {
        foreach ($lines as $line) {
            if (!$line instanceof AccountingEntry) {
                throw new AccountingEventException('All lines must be instances of AccountingEntry.');
            }
        }

        $this->lines = array_values($lines);

        return $this;
    }

    public function addLine(AccountingEntry $entry): self
    {
        $this->lines[] = $entry;

        return $this;
    }

    /**
     * @return AccountingEntry[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalDebit(): float
    {
        return $this->sumByDirection('debit');
    }

    public function totalCredit(): float
    {
        return $this->sumByDirection('credit');
    }

    public function assertBalanced(): void
    {
        $difference = abs($this->totalDebit() - $this->totalCredit());

        if ($difference > self::BALANCE_TOLERANCE) {
            throw new AccountingEventException(sprintf(
                'Accounting event %s is not balanced (Δ %s).',
                $this->code->value,
                number_format($difference, 6)
            ));
        }
    }

    public function toArray(): array
    {
        return [
            'event_code' => $this->code->value,
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'document_number' => $this->documentNumber,
            'currency_code' => $this->currencyCode,
            'exchange_rate' => $this->exchangeRate,
            'occurred_at' => $this->occurredAt->toISOString(),
            'actor_id' => $this->actorId,
            'meta' => $this->meta,
            'totals' => [
                'debit' => $this->totalDebit(),
                'credit' => $this->totalCredit(),
            ],
            'lines' => array_map(
                static fn (AccountingEntry $entry) => $entry->toArray(),
                $this->lines,
            ),
        ];
    }

    public static function fromArray(array $payload): self
    {
        $instance = new self(
            code: AccountingEventCode::from($payload['event_code']),
            companyId: (int) $payload['company_id'],
            branchId: $payload['branch_id'] !== null ? (int) $payload['branch_id'] : null,
            documentType: (string) $payload['document_type'],
            documentId: $payload['document_id'] ?? null,
            documentNumber: $payload['document_number'] ?? null,
            currencyCode: (string) $payload['currency_code'],
            exchangeRate: (float) ($payload['exchange_rate'] ?? 1),
            occurredAt: CarbonImmutable::parse($payload['occurred_at'] ?? now()),
            actorId: isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            meta: $payload['meta'] ?? [],
        );

        $lines = array_map(
            static fn (array $line) => new AccountingEntry(
                $line['role'],
                $line['direction'],
                (float) $line['amount'],
                $line['meta'] ?? [],
            ),
            $payload['lines'] ?? [],
        );

        return $instance->setLines($lines);
    }

    private function sumByDirection(string $direction): float
    {
        return array_reduce(
            $this->lines,
            static function (float $carry, AccountingEntry $entry) use ($direction): float {
                if ($entry->direction !== $direction) {
                    return $carry;
                }

                return $carry + $entry->amount;
            },
            0.0
        );
    }
}


