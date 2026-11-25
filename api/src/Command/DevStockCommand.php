<?php

declare(strict_types=1);

namespace App\Command;

use App\Clock;
use App\Context\Lead\Lead;
use App\Context\Lead\LeadStatus;
use App\Context\Lead\Sale;
use App\Context\Market\Preset;
use App\Context\Market\PresetRepository;
use App\Context\Organization\OrganizationRepository;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use App\Value\AgeBracket;
use Ramsey\Uuid\UuidFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'dev:stock',
    'Feeds the newest presets of an organization with synthetic leads (dev/stage only)',
)]
final class DevStockCommand extends Command
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users,
        private readonly PresetRepository $presets,
        private readonly Sale $sale,
        private readonly UuidFactoryInterface $uuids,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apex', null, InputOption::VALUE_REQUIRED, 'Organization apex, e.g. chase.io')
            ->addOption('presets', 'p', InputOption::VALUE_REQUIRED, 'How many newest presets to feed', '6')
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Leads per preset', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apex = trim((string) $input->getOption('apex'));
        $presetLimit = max(1, (int) $input->getOption('presets'));
        $perPreset = max(1, (int) $input->getOption('count'));
        if ($apex === '') {
            $output->writeln('Pass --apex, for example --apex=chase.io.');

            return Command::INVALID;
        }

        $organization = $this->organizations->findByApex($apex);
        if ($organization === null) {
            $output->writeln('Organization ' . $apex . ' was not found.');

            return Command::FAILURE;
        }

        $buyer = $this->buyerOf($organization->getId());
        if (!$buyer instanceof User) {
            $output->writeln('Organization ' . $apex . ' has no buyer to own the leads.');

            return Command::FAILURE;
        }

        $presets = [];
        foreach ($this->presets->findByOrganization($organization->getId()) as $preset) {
            if ($preset instanceof Preset) {
                $presets[] = $preset;
            }
            if (count($presets) >= $presetLimit) {
                break;
            }
        }
        if ($presets === []) {
            $output->writeln('Organization ' . $apex . ' has no presets.');

            return Command::FAILURE;
        }

        $stocked = 0;
        $sold = 0;
        foreach ($presets as $preset) {
            $landed = ['in_stock' => 0, 'sold' => 0];
            for ($i = 0; $i < $perPreset; $i++) {
                $placed = $this->sale->place($this->synthetic($buyer, $preset, $i));
                $landed[$placed->getStatus() === LeadStatus::InStock ? 'in_stock' : 'sold']++;
            }
            $stocked += $landed['in_stock'];
            $sold += $landed['sold'];
            $output->writeln(sprintf(
                '%s %s/%s/%s/%s → %d in stock, %d sold',
                $preset->getId(),
                $preset->getCountry(),
                $preset->getTrafficSource(),
                $preset->getAge() === '' ? 'any age' : $preset->getAge(),
                $preset->getPaymentModel(),
                $landed['in_stock'],
                $landed['sold'],
            ));
        }
        $output->writeln(sprintf(
            'Fed %d preset(s): %d in stock, %d sold into open orders.',
            count($presets),
            $stocked,
            $sold,
        ));

        return Command::SUCCESS;
    }

    private function buyerOf(string $organizationId): ?User
    {
        foreach ($this->users->findByOrganization($organizationId) as $user) {
            if ($user instanceof User && $user->getRole() === UserRole::Buyer) {
                return $user;
            }
        }

        return null;
    }

    private function synthetic(User $buyer, Preset $preset, int $seq): Lead
    {
        $now = $this->clock->now();
        $uuid = $this->uuids->uuid4()->toString();
        $gender = $preset->getGender() === 'all'
            ? ($seq % 2 === 0 ? 'm' : 'f')
            : $preset->getGender();

        return new Lead(
            $uuid,
            $buyer->getOrganizationId(),
            $buyer->getId(),
            null,
            null,
            'stock' . $seq . '@' . $preset->getCountry() . '.dev.local',
            '+1555' . str_pad((string) random_int(1_000_000, 9_999_999), 7, '0', STR_PAD_LEFT),
            'Stock',
            'Lead' . $seq,
            $preset->getCountry(),
            $preset->getTrafficSource(),
            $this->ageInside($preset->getAge()),
            $gender,
            '198.51.100.' . (($seq % 250) + 1),
            'aff-' . $preset->getVertical(),
            'uniq-' . $uuid,
            LeadStatus::New,
            0,
            $now,
            $now,
        );
    }

    private function ageInside(string $bracket): string
    {
        $span = AgeBracket::span($bracket);
        if ($span === null) {
            return '30';
        }

        return (string) $span[0];
    }
}
