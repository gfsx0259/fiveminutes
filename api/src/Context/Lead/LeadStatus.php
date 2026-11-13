<?php

declare(strict_types=1);

namespace App\Context\Lead;

enum LeadStatus: string
{
    /** Принят API и ещё не разложен матчером. В ClickHouse так не пишем. */
    case New = 'new';

    /** Продан в принятый слот или заявку. */
    case Sold = 'sold';

    /** На складе байера: продавать сейчас некуда. */
    case InStock = 'in_stock';

    /** Доставлен. Для CPL это момент списания. */
    case Delivered = 'delivered';

    /** Одобрен. Для CPA это момент списания. */
    case Approved = 'approved';

    /** Отклонён. Списание по нему не делаем. */
    case Rejected = 'rejected';
}
