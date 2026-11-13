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

        $I->sendGet('/slots');
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

    public function buyerAndPartnerTradeALead(AcceptanceTester $I): void
    {
        $I->signIn('buyer');
        $I->sendGet('/session');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['user' => ['email' => 'buyer@chase.io', 'role' => 'buyer', 'organization' => ['apex' => 'chase.io']]]);

        $I->signIn('partner');
        $I->sendGet('/session');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['user' => ['email' => 'partner@chase.io', 'role' => 'partner']]);

        $I->sendPost('/slots', $this->slot('DE'));
        $I->seeResponseCodeIs(403);
        $I->seeResponseContainsJson(['error' => 'Only a buyer can open a slot']);

        $I->signIn('buyer');
        $I->sendPost('/demands', $this->demand('DE'));
        $I->seeResponseCodeIs(403);
        $I->seeResponseContainsJson(['error' => 'Only a partner can open a demand']);

        $I->sendPost('/slots', []);
        $I->seeResponseCodeIs(400);
        $I->seeResponseContainsJson(['errors' => ['geo' => 'Geo must be a country code, like IT']]);

        [$slotGeo, $demandGeo, $stockGeo] = (new GeoPool())->take($this->usedGeos($I), 3);
        $I->signIn('buyer');
        $deadline = gmdate('Y-m-d', time() + 7 * 86400);

        $I->sendPost('/slots', $this->slot($slotGeo, $deadline, 60));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'open']);
        $slotId = $this->body($I)['id'];

        $I->sendPost('/slots/' . $slotId . '/accept');
        $I->seeResponseCodeIs(403);
        $I->seeResponseContainsJson(['error' => 'Only a partner can accept a slot']);

        $I->signIn('partner');
        $I->sendPost('/slots/' . $slotId . '/accept');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'taken']);

        $I->sendPost('/demands', $this->demand($demandGeo, 40));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'open']);
        $demandId = $this->body($I)['id'];

        $I->sendPost('/demands/' . $demandId . '/accept');
        $I->seeResponseCodeIs(403);
        $I->seeResponseContainsJson(['error' => 'Only a buyer can accept a demand']);

        $I->signIn('buyer');
        $I->sendPost('/demands/' . $demandId . '/accept');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'taken']);

        $I->sendPost('/api-key');
        $I->seeResponseCodeIs(200);
        $apiKey = $this->body($I)['apiKey'];
        $I->haveHttpHeader('Authorization', 'Bearer ' . $apiKey);

        $I->sendPost('/v1/lead', $this->lead($slotGeo));
        $I->seeResponseCodeIs(200);
        $soldOnSlot = $this->body($I)['uuid'];

        $I->sendPost('/v1/lead', $this->lead($demandGeo));
        $I->seeResponseCodeIs(200);
        $soldOnDemand = $this->body($I)['uuid'];

        $I->sendPost('/v1/lead', $this->lead($stockGeo));
        $I->seeResponseCodeIs(200);
        $stocked = $this->body($I)['uuid'];

        $I->deleteHeader('Authorization');
        $slotLead = $I->waitForLead($soldOnSlot, 'sold');
        $I->assertSame(6000, $slotLead['priceCents']);
        $I->assertSame($slotGeo, $slotLead['country']);

        $demandLead = $I->waitForLead($soldOnDemand, 'sold');
        $I->assertSame(4000, $demandLead['priceCents']);

        $stockLead = $I->waitForLead($stocked, 'in_stock');
        $I->assertSame(0, $stockLead['priceCents']);
        $I->assertNull($stockLead['sellerId']);

        $I->signIn('partner');
        $I->sendGet('/leads');
        $I->seeResponseCodeIs(200);
        $partnerLeads = $this->body($I)['leads'];
        $I->assertNotFalse($this->find($partnerLeads, $soldOnSlot));
        $I->assertNotFalse($this->find($partnerLeads, $soldOnDemand));
        $I->assertFalse($this->find($partnerLeads, $stocked));

        $I->haveHttpHeader('Authorization', 'Bearer nope');
        $I->sendPost('/v1/lead', $this->lead($stockGeo));
        $I->seeResponseCodeIs(401);

        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $apiKey);
        $I->sendPost('/v1/lead', ['phone' => '123']);
        $I->seeResponseCodeIs(400);
        $I->seeResponseContainsJson(['errors' => ['phone' => 'Phone must be E.164, like +4915112345678']]);
    }

    /**
     * @return array<string, true>
     */
    private function usedGeos(AcceptanceTester $I): array
    {
        $used = [];
        foreach (['buyer', 'partner'] as $who) {
            $I->signIn($who);
            foreach (['/slots', '/demands'] as $path) {
                $I->sendGet($path);
                $I->seeResponseCodeIs(200);
                $key = $path === '/slots' ? 'slots' : 'demands';
                foreach ($this->body($I)[$key] as $row) {
                    $used[$row['geo']] = true;
                }
            }
        }

        return $used;
    }

    /**
     * @return array<string, mixed>
     */
    private function slot(string $geo, string $deadline = '2026-12-01', int $price = 50): array
    {
        return [
            'geo' => $geo,
            'volume' => 5,
            'price' => $price,
            'pricing_model' => 'cpl',
            'deadline' => $deadline,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function demand(string $geo, int $price = 40): array
    {
        return [
            'geo' => $geo,
            'volume' => 5,
            'price' => $price,
            'pricing_model' => 'cpl',
            'service_on' => gmdate('Y-m-d'),
            'opens_at' => '00:00',
            'closes_at' => '23:59',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function lead(string $geo): array
    {
        return [
            'phone' => '+1' . random_int(2000000000, 9999999999),
            'email' => 'lead@example.com',
            'country_code' => $geo,
            'ip' => '203.0.113.10',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'aff_sub' => 'flow',
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

    /**
     * @param list<array<string, mixed>> $leads
     * @return array<string, mixed>|false
     */
    private function find(array $leads, string $uuid): array|false
    {
        foreach ($leads as $lead) {
            if (($lead['uuid'] ?? '') === $uuid) {
                return $lead;
            }
        }

        return false;
    }
}
