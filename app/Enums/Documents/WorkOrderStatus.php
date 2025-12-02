<?php

namespace App\Enums\Documents;

enum WorkOrderStatus: string
{
    case DRAFT = 'draft';
    case RELEASED = 'released';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CLOSED = 'closed';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::RELEASED => 'Released',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CLOSED => 'Closed',
            self::CANCELED => 'Canceled',
        };
    }
}


