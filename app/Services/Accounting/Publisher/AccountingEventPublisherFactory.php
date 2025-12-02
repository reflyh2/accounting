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
        $driver ??= $this->config['driver'] ?? 'log';

        return match ($driver) {
            'log' => new LogAccountingEventPublisher(
                $this->config['log_channel'] ?? 'stack'
            ),
            default => throw new InvalidArgumentException("Unsupported accounting event driver [{$driver}]."),
        };
    }
}


