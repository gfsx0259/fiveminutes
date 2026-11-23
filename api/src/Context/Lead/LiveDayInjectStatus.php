<?php

declare(strict_types=1);

namespace App\Context\Lead;

enum LiveDayInjectStatus: string
{
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
}