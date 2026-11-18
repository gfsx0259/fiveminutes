<?php

declare(strict_types=1);

namespace App\Context\User;

enum UserRole: string
{
    /** Поставляет лиды. Кабинет buyer.<apex>. */
    case Buyer = 'buyer';

    /** Покупает пачку. Кабинет seller.<apex>. */
    case Partner = 'partner';

    /** Заводит пресеты и модерирует предложения. Кабинет admin.<apex>. */
    case Admin = 'admin';
}
