<?php

namespace ZfrEbWorker\Container;

use Aws\Sdk as AwsSdk;
use Interop\Container\ContainerInterface;
use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\Queue\MessageQueueRepository;

/**
 * @author Michaël Gallego
 */
class QueueRepositoryFactory
{
    /**
     * @param  ContainerInterface $container
     * @return MessageQueueRepository
     */
    public function __invoke(ContainerInterface $container): MessageQueueRepository
    {
        $config = $container->get('config');

        if (!isset($config['zfr_eb_worker'])) {
            throw new RuntimeException('Key "zfr_eb_worker" is missing');
        }

        /** @var AwsSdk $awsSdk */
        $awsSdk    = $container->get(AwsSdk::class);
        $sqsClient = $awsSdk->createSqs();

        return new MessageQueueRepository($config['zfr_eb_worker']['queues'], $sqsClient);
    }
}