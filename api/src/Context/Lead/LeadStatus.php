<?php

declare(strict_types=1);

namespace App\Context\Lead;

enum LeadStatus: string
{
    /** Лид встал в сделку, деньги ещё не двигались. */
    case New = 'new';

    /** Доставлен. Для CPL это момент списания. */
    case Delivered = 'delivered';

    /** Одобрен. Для CPA это момент списания. */
    case Approved = 'approved';

    /** Отклонён. Списание по нему не делаем. */
    case Rejected = 'rejected';
}
