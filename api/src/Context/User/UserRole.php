<?php

declare(strict_types=1);

namespace App\Context\User;

enum UserRole: string
{
    /** Майнт лиды и выставляет слот. В прототипе это buyer. */
    case Buyer = 'buyer';

    /** Принимает слот и создаёт demand. В прототипе это seller, в LM это партнёр. */
    case Partner = 'partner';
}
