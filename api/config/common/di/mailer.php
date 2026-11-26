<?php

declare(strict_types=1);

use App\Context\Team\TeamMailer;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

return [
    MailerInterface::class => static fn (): MailerInterface => new Mailer(
        Transport::fromDsn(($_ENV['RIDER_MAILER_DSN'] ?? '') !== '' ? $_ENV['RIDER_MAILER_DSN'] : 'null://null'),
    ),
    TeamMailer::class => [
        '__construct()' => [
            'from' => ($_ENV['RIDER_MAIL_FROM'] ?? '') !== '' ? $_ENV['RIDER_MAIL_FROM'] : 'Rider <no-reply@rider.io>',
        ],
    ],
];
