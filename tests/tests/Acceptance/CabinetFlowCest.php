<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\GeoPool;
use Tests\Support\AcceptanceTester;

final class CabinetFlowCest
{
    public function guestIsTurnedAway(AcceptanceTester $I): void
    {
        $I->asGuest();
        $I->sendGet('/health');
        $I->seeResponseCodeIs(200);

        $I->sendGet('/session');
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['error' => 'No session']);

        $I->sendGet('/orders');
        $I->seeResponseCodeIs(401);

        $I->sendPost('/v1/lead', $this->lead('DE'));
        $I->seeResponseCodeIs(401);
    }

    public function strangerCannotRegister(AcceptanceTester $I): void
    {
        $I->asGuest();
        $I->sendPost('/user/registration', [
            'userId' => '00000000-0000-4000-8000-000000000001',
            'email' => 'nobody-' . bin2hex(random_bytes(4)) . '@chase.io',
        ]);
        $I->seeResponseCodeIs(400);
    }

    public function ordersShareALeadByWeight(AcceptanceTester $I): void
    {
        $I->signIn('partner');
        $I->sendPost('/presets', $this->preset());
        $I->seeResponseCodeIs(403);

        $traffic = 'fb' . bin2hex(random_bytes(3));
        $geo = (new GeoPool())->take($this->usedGeos($I), 1)[0];
        $I->signIn('admin');
        $I->sendPost('/presets', $this->preset(0, $traffic, $geo));
        $I->seeResponseCodeIs(400);

        $I->sendPost('/presets', $this->preset(40, $traffic, $geo));
        $I->seeResponseCodeIs(200);
        $presetId = $this->body($I)['id'];
        $today = gmdate('Y-m-d');

        $I->signIn('buyer');
        $I->sendPost('/orders', $this->order($presetId, $geo, 40, $today));
        $I->seeResponseCodeIs(403);

        $I->signIn('partner');
        $I->sendPost('/orders', $this->order($presetId, $geo, 30, $today));
        $I->seeResponseCodeIs(400);
        $I->seeResponseContainsJson(['error' => 'Price cannot be below the floor price']);

        $I->sendPost('/orders', $this->order($presetId, $geo, 80, $today));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'in_progress']);
        $I->sendPost('/orders', $this->order($presetId, $geo, 40, $today));
        $I->seeResponseCodeIs(200);

        $I->signIn('buyer');
        $I->sendPost('/offers', $this->offer($presetId, $geo, $today));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'pending']);
        $offerId = $this->body($I)['id'];

        $I->sendPost('/offers/' . $offerId . '/approve');
        $I->seeResponseCodeIs(403);

        $I->signIn('admin');
        $I->sendPost('/offers/' . $offerId . '/approve');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'approved']);

        $I->signIn('buyer');
        $I->sendPost('/api-key');
        $I->seeResponseCodeIs(200);
        $apiKey = $this->body($I)['apiKey'];
        $I->haveHttpHeader('Authorization', 'Bearer ' . $apiKey);

        $I->sendPost('/v1/lead', $this->lead('FR', 'other'));
        $I->seeResponseCodeIs(400);
        $I->seeResponseContainsJson(['errors' => ['preset' => 'No preset matches this lead']]);

        $sold = [];
        for ($index = 0; $index < 6; $index++) {
            $I->sendPost('/v1/lead', $this->lead($geo, $traffic));
            $I->seeResponseCodeIs(200);
            $sold[] = $this->body($I)['uuid'];
        }

        $I->deleteHeader('Authorization');
        $I->signIn('partner');
        $prices = [];
        foreach ($sold as $uuid) {
            $lead = $I->waitForLead($uuid, 'sold');
            $prices[] = $lead['priceCents'];
        }
        $I->assertSame(4, count(array_filter($prices, static fn (int $price): bool => $price === 8000)));
        $I->assertSame(2, count(array_filter($prices, static fn (int $price): bool => $price === 4000)));

        $I->haveHttpHeader('Authorization', 'Bearer nope');
        $I->sendPost('/v1/lead', $this->lead($geo));
        $I->seeResponseCodeIs(401);
    }

    /**
     * @return array<string, true>
     */
    private function usedGeos(AcceptanceTester $I): array
    {
        $used = [];
        $I->signIn('partner');
        $I->sendGet('/orders');
        $I->seeResponseCodeIs(200);
        foreach ($this->body($I)['orders'] as $row) {
            $used[$row['geo']] = true;
        }

        return $used;
    }

    /**
     * @return array<string, mixed>
     */
    private function preset(int $floorPrice = 40, string $traffic = 'facebook', string $country = 'DE'): array
    {
        $floor = $floorPrice === 0 ? 40 : $floorPrice;

        return [
            'country' => $country,
            'traffic_source' => $traffic,
            'age' => '25-34',
            'gender' => 'm',
            'vertical' => 'gambling',
            'payment_model' => 'cpl',
            'cr' => $floorPrice === 0 ? 120 : 20,
            'price' => $floor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function order(string $presetId, string $geo, int $price, string $day): array
    {
        return [
            'preset_id' => $presetId,
            'geo' => $geo,
            'volume' => 10,
            'price' => $price,
            'payment_model' => 'cpl',
            'kind' => 'day',
            'service_on' => $day,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function offer(string $presetId, string $geo, string $day): array
    {
        return [
            'preset_id' => $presetId,
            'geo' => $geo,
            'volume' => 6,
            'kind' => 'day',
            'service_on' => $day,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function lead(string $geo, string $traffic = 'facebook'): array
    {
        return [
            'phone' => '+1' . random_int(2000000000, 9999999999),
            'email' => 'lead@example.com',
            'country_code' => $geo,
            'ip' => '203.0.113.10',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'vertical' => 'other',
            'traffic_source' => $traffic,
            'age' => '25-34',
            'gender' => 'm',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function body(AcceptanceTester $I): array
    {
        $decoded = json_decode($I->grabResponse(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
