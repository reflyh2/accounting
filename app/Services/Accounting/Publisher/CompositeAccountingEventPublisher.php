<?php

namespace App\Services\Accounting\Publisher;

use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Models\AccountingEventLog;

class CompositeAccountingEventPublisher implements AccountingEventPublisher
{
    /**
     * @param AccountingEventPublisher[] $publishers
     */
    public function __construct(private array $publishers)
    {
    }

    public function send(AccountingEventPayload $payload, AccountingEventLog $log): void
    {
        foreach ($this->publishers as $publisher) {
            $publisher->send($payload, $log);
        }
    }
}
