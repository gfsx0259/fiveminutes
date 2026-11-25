<?php

declare(strict_types=1);

namespace App\Context\User;

enum UserRole: string
{
    /** Поставляет лиды. Кабинет buyer.<apex>. */
    case Buyer = 'buyer';

    /** Покупает пачку. Кабинет partner.<apex>. */
    case Partner = 'partner';

    /** Заводит пресеты и модерирует предложения. Кабинет admin.<apex>. */
    case Admin = 'admin';
}
