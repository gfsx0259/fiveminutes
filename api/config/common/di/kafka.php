<?php

declare(strict_types=1);

use App\Context\Lead\LeadInbox;
use App\Context\Lead\LeadQueue;
use App\Context\Lead\MinedLeadMessage;
use App\Context\Wallet\SettlementInbox;
use App\Context\Wallet\SettlementQueue;
use longlang\phpkafka\Consumer\Consumer;
use longlang\phpkafka\Consumer\ConsumerConfig;
use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;

return [
    LeadQueue::class => static function (MinedLeadMessage $message): LeadQueue {
        $config = new ProducerConfig();
        $config->setBootstrapServer($_ENV['RIDER_KAFKA_BROKER']);
        $config->setUpdateBrokers(true);
        $config->setAcks(-1);

        $topic = $_ENV['RIDER_KAFKA_MATCHER_TOPIC'] ?? 'rider.leads';

        return new LeadQueue(new Producer($config), $message, $topic);
    },
    LeadInbox::class => static function (MinedLeadMessage $message): LeadInbox {
        $config = new ConsumerConfig();
        $config->setBootstrapServer($_ENV['RIDER_KAFKA_BROKER']);
        $config->setUpdateBrokers(true);
        $config->setGroupId($_ENV['RIDER_KAFKA_MATCHER_GROUP'] ?? 'rider-matcher');
        $config->setTopic($_ENV['RIDER_KAFKA_MATCHER_TOPIC'] ?? 'rider.leads');
        $config->setAutoCommit(false);
        $config->setAutoCreateTopic(true);
        $config->setInterval(0.2);

        return new LeadInbox(new Consumer($config), $message);
    },
    SettlementQueue::class => static function (): SettlementQueue {
        $config = new ProducerConfig();
        $config->setBootstrapServer($_ENV['RIDER_KAFKA_BROKER']);
        $config->setUpdateBrokers(true);
        $config->setAcks(-1);
        $topic = $_ENV['RIDER_KAFKA_SETTLEMENT_TOPIC'] ?? 'rider.lead.settlement';

        return new SettlementQueue(new Producer($config), $topic);
    },
    SettlementInbox::class => static function (): SettlementInbox {
        $config = new ConsumerConfig();
        $config->setBootstrapServer($_ENV['RIDER_KAFKA_BROKER']);
        $config->setUpdateBrokers(true);
        $config->setGroupId($_ENV['RIDER_KAFKA_SETTLEMENT_GROUP'] ?? 'rider-balance');
        $config->setTopic($_ENV['RIDER_KAFKA_SETTLEMENT_TOPIC'] ?? 'rider.lead.settlement');
        $config->setAutoCommit(false);
        $config->setAutoCreateTopic(true);
        $config->setInterval(0.2);

        return new SettlementInbox(new Consumer($config));
    },
];
