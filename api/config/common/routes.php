<?php

declare(strict_types=1);

use App\Api\ApiKeyController;
use App\Api\HealthController;
use App\Api\IdentityController;
use App\Api\ImpersonationController;
use App\Api\LeadController;
use App\Api\MarketController;
use App\Api\OrganizationController;
use App\Api\RegistrationController;
use App\Api\SessionController;
use App\Api\UserController;
use App\Api\WalletController;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()->routes(
        Route::get('/health')->action([HealthController::class, 'index'])->name('health'),
        Route::post('/user/registration')->action([RegistrationController::class, 'create'])->name('user-registration'),
        Route::get('/session')->action([SessionController::class, 'show'])->name('session'),
        Route::get('/identity/settings-flow')->action([IdentityController::class, 'settingsFlow'])->name('identity-settings-flow'),
        Route::post('/identity/settings')->action([IdentityController::class, 'updateSettings'])->name('identity-settings-update'),
        Route::get('/identity/sessions')->action([IdentityController::class, 'sessions'])->name('identity-sessions'),
        Route::delete('/identity/sessions/others')->action([IdentityController::class, 'revokeOtherSessions'])->name('identity-sessions-others'),
        Route::delete('/identity/sessions/{id}')->action([IdentityController::class, 'revokeSession'])->name('identity-session-revoke'),
        Route::get('/users')->action([UserController::class, 'index'])->name('users'),
        Route::post('/impersonate')->action([ImpersonationController::class, 'start'])->name('impersonate-start'),
        Route::delete('/impersonate')->action([ImpersonationController::class, 'stop'])->name('impersonate-stop'),
        Route::post('/organization/markup')->action([OrganizationController::class, 'setMarkup'])->name('organization-markup'),
        Route::get('/organization/shelf-age-policy')->action([OrganizationController::class, 'shelfAgePolicy'])->name('organization-shelf-age-policy'),
        Route::post('/organization/shelf-age-policy')->action([OrganizationController::class, 'setShelfAgePolicy'])->name('organization-shelf-age-policy-set'),
        Route::patch('/organization/shelf-age-policy')->action([OrganizationController::class, 'setShelfAgePolicy'])->name('organization-shelf-age-policy-patch'),
        Route::get('/wallet/transactions')->action([WalletController::class, 'transactions'])->name('wallet-transactions'),
        Route::post('/wallet/deposit')->action([WalletController::class, 'deposit'])->name('wallet-deposit'),
        Route::post('/wallet/withdraw')->action([WalletController::class, 'withdraw'])->name('wallet-withdraw'),
        Route::post('/v1/lead')->action([LeadController::class, 'create'])->name('lead-create'),
        Route::get('/leads')->action([LeadController::class, 'index'])->name('leads'),
        Route::get('/leads/{uuid}')->action([LeadController::class, 'show'])->name('lead-show'),
        Route::post('/leads/{uuid}/approve')->action([LeadController::class, 'approve'])->name('lead-approve'),
        Route::post('/leads/{uuid}/reject')->action([LeadController::class, 'reject'])->name('lead-reject'),
        Route::post('/leads/{uuid}/deliver')->action([LeadController::class, 'deliver'])->name('lead-deliver'),
        Route::get('/api-key')->action([ApiKeyController::class, 'show'])->name('api-key'),
        Route::post('/api-key')->action([ApiKeyController::class, 'create'])->name('api-key-create'),
        Route::get('/presets')->action([MarketController::class, 'presets'])->name('presets'),
        Route::post('/presets')->action([MarketController::class, 'openPreset'])->name('preset-create'),
        Route::delete('/presets/{id}')->action([MarketController::class, 'deletePreset'])->name('preset-delete'),
        Route::get('/orders')->action([MarketController::class, 'orders'])->name('orders'),
        Route::post('/orders')->action([MarketController::class, 'openOrder'])->name('order-create'),
        Route::post('/orders/{id}/pause')->action([MarketController::class, 'pauseOrder'])->name('order-pause'),
        Route::post('/orders/{id}/resume')->action([MarketController::class, 'resumeOrder'])->name('order-resume'),
        Route::post('/orders/{id}/finish')->action([MarketController::class, 'finishOrder'])->name('order-finish'),
        Route::get('/warehouse')->action([MarketController::class, 'warehouse'])->name('warehouse'),
        Route::get('/warehouse/stock/quote')->action([MarketController::class, 'stockQuote'])->name('warehouse-stock-quote'),
        Route::get('/caps')->action([MarketController::class, 'caps'])->name('caps'),
        Route::get('/offers')->action([MarketController::class, 'offers'])->name('offers'),
        Route::post('/offers')->action([MarketController::class, 'openOffer'])->name('offer-create'),
        Route::post('/offers/{id}/approve')->action([MarketController::class, 'approveOffer'])->name('offer-approve'),
        Route::post('/offers/{id}/reject')->action([MarketController::class, 'rejectOffer'])->name('offer-reject'),
    ),
];