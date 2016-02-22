<?php

namespace ZfrEbWorker\Container;

use Aws\Sdk as AwsSdk;
use Interop\Container\ContainerInterface;
use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\Publisher\QueuePublisherInterface;

/**
 * @author Michaël Gallego
 */
class PublisherCommandFactory
{
    /**
     * @param  ContainerInterface $container
     * @return PublisherCommand
     */
    public function __invoke(ContainerInterface $container): PublisherCommand
    {
        /** @var QueuePublisherInterface $queuePublisher */
        $queuePublisher = $container->get(QueuePublisherInterface::class);

        /** @var AwsSdk $awsSdk */
        $awsSdk    = $container->get(AwsSdk::class);
        $sqsClient = $awsSdk->createSqs();

        return new PublisherCommand($queuePublisher, $sqsClient);
    }
}
