<?php

namespace App\Enums;

enum AccountingEventStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED], true);
    }
}


