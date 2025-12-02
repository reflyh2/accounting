<?php

namespace App\Services\Accounting;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use Carbon\CarbonInterface;

class AccountingEventBuilder
{
    /**
     * @var AccountingEntry[]
     */
    private array $entries = [];

    private function __construct(private AccountingEventPayload $payload)
    {
    }

    /**
     * @param array{
     *     company_id:int,
     *     branch_id?:int|null,
     *     document_type:string,
     *     document_id?:int|string|null,
     *     document_number?:string|null,
     *     currency_code:string,
     *     exchange_rate?:float,
     *     occurred_at?:CarbonInterface,
     *     actor_id?:int|null,
     *     meta?:array
     * } $attributes
     */
    public static function forDocument(AccountingEventCode $code, array $attributes): self
    {
        $payload = new AccountingEventPayload(
            code: $code,
            companyId: $attributes['company_id'],
            branchId: $attributes['branch_id'] ?? null,
            documentType: $attributes['document_type'],
            documentId: $attributes['document_id'] ?? null,
            documentNumber: $attributes['document_number'] ?? null,
            currencyCode: $attributes['currency_code'],
            exchangeRate: $attributes['exchange_rate'] ?? 1.0,
            occurredAt: $attributes['occurred_at'] ?? now(),
            actorId: $attributes['actor_id'] ?? null,
            meta: $attributes['meta'] ?? [],
        );

        return new self($payload);
    }

    public function debit(string $role, float $amount, array $meta = []): self
    {
        $this->entries[] = AccountingEntry::debit($role, $amount, $meta);

        return $this;
    }

    public function credit(string $role, float $amount, array $meta = []): self
    {
        $this->entries[] = AccountingEntry::credit($role, $amount, $meta);

        return $this;
    }

    /**
     * @param AccountingEntry[] $entries
     */
    public function withEntries(array $entries): self
    {
        foreach ($entries as $entry) {
            $this->entries[] = $entry;
        }

        return $this;
    }

    public function build(): AccountingEventPayload
    {
        return $this->payload->setLines($this->entries);
    }
}


