<?php

declare(strict_types=1);

use App\Api\HealthController;
use App\Api\RegistrationController;
use App\Api\SessionController;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()->routes(
        Route::get('/health')->action([HealthController::class, 'index'])->name('health'),
        Route::post('/user/registration')->action([RegistrationController::class, 'create'])->name('user-registration'),
        Route::get('/session')->action([SessionController::class, 'show'])->name('session'),
    ),
];
