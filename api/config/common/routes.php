<?php

declare(strict_types=1);

use App\Api\ApiKeyController;
use App\Api\HealthController;
use App\Api\LeadController;
use App\Api\MarketController;
use App\Api\RegistrationController;
use App\Api\SessionController;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()->routes(
        Route::get('/health')->action([HealthController::class, 'index'])->name('health'),
        Route::post('/user/registration')->action([RegistrationController::class, 'create'])->name('user-registration'),
        Route::get('/session')->action([SessionController::class, 'show'])->name('session'),
        Route::post('/v1/lead')->action([LeadController::class, 'create'])->name('lead-create'),
        Route::get('/leads')->action([LeadController::class, 'index'])->name('leads'),
        Route::get('/api-key')->action([ApiKeyController::class, 'show'])->name('api-key'),
        Route::post('/api-key')->action([ApiKeyController::class, 'create'])->name('api-key-create'),
        Route::get('/slots')->action([MarketController::class, 'slots'])->name('slots'),
        Route::post('/slots')->action([MarketController::class, 'openSlot'])->name('slot-create'),
        Route::post('/slots/{id}/accept')->action([MarketController::class, 'acceptSlot'])->name('slot-accept'),
        Route::get('/demands')->action([MarketController::class, 'demands'])->name('demands'),
        Route::post('/demands')->action([MarketController::class, 'openDemand'])->name('demand-create'),
        Route::post('/demands/{id}/accept')->action([MarketController::class, 'acceptDemand'])->name('demand-accept'),
    ),
];
