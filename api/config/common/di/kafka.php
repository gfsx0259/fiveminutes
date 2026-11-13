<?php

declare(strict_types=1);

use App\Context\Lead\LeadInbox;
use App\Context\Lead\LeadQueue;
use App\Context\Lead\MinedLeadMessage;
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

        return new LeadQueue(new Producer($config), $message, $_ENV['RIDER_KAFKA_TOPIC']);
    },
    LeadInbox::class => static function (MinedLeadMessage $message): LeadInbox {
        $config = new ConsumerConfig();
        $config->setBootstrapServer($_ENV['RIDER_KAFKA_BROKER']);
        $config->setUpdateBrokers(true);
        $config->setGroupId($_ENV['RIDER_KAFKA_GROUP']);
        $config->setTopic($_ENV['RIDER_KAFKA_TOPIC']);
        $config->setAutoCommit(false);
        $config->setAutoCreateTopic(true);
        $config->setInterval(0.2);

        return new LeadInbox(new Consumer($config), $message);
    },
];
