<?php

namespace App\Services\Accounting\Publisher;

use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Models\AccountingEventLog;

interface AccountingEventPublisher
{
    public function send(AccountingEventPayload $payload, AccountingEventLog $log): void;
}


