<?php

namespace App\Services\Accounting\Publisher;

use InvalidArgumentException;

class AccountingEventPublisherFactory
{
    public function __construct(private array $config = [])
    {
    }

    public function make(?string $driver = null): AccountingEventPublisher
    {
        // By default, or explicitly, we now want to use a Composite strategy that includes both Logging and Journaling.
        // We can make this configurable, but for now we'll hardcode the requirement: "Both logging and journaling".

        $publishers = [];

        // 1. Log Publisher (Always enabled or configurable)
        $publishers[] = new LogAccountingEventPublisher(
            $this->config['log_channel'] ?? 'stack'
        );

        // 2. Journal Publisher (Always enabled or configurable)
        $publishers[] = new JournalAccountingEventPublisher();

        return new CompositeAccountingEventPublisher($publishers);
    }
}


