<?php

declare(strict_types=1);

namespace App\Context\Team;

enum Permission: string
{
    case LeadsView = 'leads.view';
    case LeadsManage = 'leads.manage';
    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case WalletView = 'wallet.view';
    case WalletManage = 'wallet.manage';
    case TeamManage = 'team.manage';

    public function section(): string
    {
        return match ($this) {
            self::LeadsView, self::LeadsManage => 'Leads',
            self::OrdersView, self::OrdersManage => 'Orders',
            self::ReportsView, self::ReportsExport => 'Reports',
            self::WalletView, self::WalletManage => 'Wallet',
            self::TeamManage => 'Team',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LeadsView => 'See lead contacts',
            self::LeadsManage => 'Work with leads',
            self::OrdersView => 'See orders and stock',
            self::OrdersManage => 'Buy leads and manage orders',
            self::ReportsView => 'See dashboard and reports',
            self::ReportsExport => 'Export CSV',
            self::WalletView => 'See balance and transactions',
            self::WalletManage => 'Add funds',
            self::TeamManage => 'Lead the team',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LeadsView => 'Email and phone of bought leads, hidden without this right',
            self::LeadsManage => 'Approve, reject and deliver leads',
            self::OrdersView => 'My orders and Buy leads pages',
            self::OrdersManage => 'Buy from stock, create, pause, resume and finish orders',
            self::ReportsView => 'Dashboard and Reports pages',
            self::ReportsExport => 'Download reports, orders, leads and transactions',
            self::WalletView => 'Account balance and Transactions page',
            self::WalletManage => 'Top up and withdraw the account balance',
            self::TeamManage => 'Invite people, build sub-teams and hand out budgets below their own place',
        };
    }
}
