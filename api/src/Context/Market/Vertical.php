<?php

declare(strict_types=1);

namespace App\Context\Market;

enum Vertical: string
{
    case Gambling = 'gambling';
    case Betting = 'betting';
    case Crypto = 'crypto';
    case Forex = 'forex';
    case Dating = 'dating';
    case Nutra = 'nutra';
    case Finance = 'finance';
    case Other = 'other';
}