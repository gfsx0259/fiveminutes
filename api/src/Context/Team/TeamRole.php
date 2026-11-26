<?php

declare(strict_types=1);

namespace App\Context\Team;

enum TeamRole: string
{
    case View = 'view';
    case Edit = 'edit';
    case Finance = 'finance';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::View => 'View',
            self::Edit => 'Edit',
            self::Finance => 'Finance',
            self::Full => 'Lead',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::View => 'Sees everything in their team, changes nothing',
            self::Edit => 'Buys leads, runs orders and works with leads, cannot add funds',
            self::Finance => 'Budget, spend and reports, lead contacts hidden',
            self::Full => 'Every action, leads people and sub-teams below',
        };
    }

    public function permissions(): Permissions
    {
        $view = [
            Permission::LeadsView,
            Permission::OrdersView,
            Permission::ReportsView,
            Permission::WalletView,
        ];

        return match ($this) {
            self::View => new Permissions($view),
            self::Edit => new Permissions([
                ...$view,
                Permission::LeadsManage,
                Permission::OrdersManage,
                Permission::ReportsExport,
            ]),
            self::Finance => new Permissions([
                Permission::OrdersView,
                Permission::ReportsView,
                Permission::ReportsExport,
                Permission::WalletView,
                Permission::WalletManage,
            ]),
            self::Full => new Permissions(Permission::cases()),
        };
    }

    public function matches(Permissions $permissions): bool
    {
        return $this->permissions()->equals($permissions);
    }
}
