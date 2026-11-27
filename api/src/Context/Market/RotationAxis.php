<?php

declare(strict_types=1);

namespace App\Context\Market;

enum RotationAxis: string
{
    case Vertical = 'vertical';
    case Country = 'country';
    case TrafficSource = 'traffic_source';
    case PaymentModel = 'payment_model';
    case Age = 'age';
    case Gender = 'gender';
}
