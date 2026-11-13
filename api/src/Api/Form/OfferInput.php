<?php

declare(strict_types=1);

namespace App\Api\Form;

use App\Context\Market\PricingModel;

/**
 * @property string $geo
 * @property int $volume
 * @property int $price
 * @property string $pricing_model
 */
trait OfferInput
{
    public function geo(): string
    {
        return $this->geo;
    }

    public function volume(): int
    {
        return $this->volume;
    }

    public function priceCents(): int
    {
        return $this->price * 100;
    }

    public function pricingModel(): PricingModel
    {
        return PricingModel::from($this->pricing_model);
    }
}
