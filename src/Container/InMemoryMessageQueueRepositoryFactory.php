<?php

namespace ZfrEbWorker\Container;

use Aws\Sdk as AwsSdk;
use Interop\Container\ContainerInterface;
use ZfrEbWorker\Exception\RuntimeException;
use ZfrEbWorker\MessageQueue\InMemoryMessageQueueRepository;

/**
 * @author Michaël Gallego
 */
class InMemoryMessageQueueRepositoryFactory
{
    /**
     * @param  ContainerInterface $container
     * @return InMemoryMessageQueueRepository
     */
    public function __invoke(ContainerInterface $container): InMemoryMessageQueueRepository
    {
        $config = $container->get('config');

        if (!isset($config['zfr_eb_worker'])) {
            throw new RuntimeException('Key "zfr_eb_worker" is missing');
        }

        /** @var AwsSdk $awsSdk */
        $awsSdk    = $container->get(AwsSdk::class);
        $sqsClient = $awsSdk->createSqs();

        return new InMemoryMessageQueueRepository($config['zfr_eb_worker']['queues'], $sqsClient);
    }
}
