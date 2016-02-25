<?php

namespace ZfrEbWorker\Container;

use Interop\Container\ContainerInterface;
use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\MessageQueue\MessageQueueRepository;

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
        /** @var MessageQueueRepository $queueRepository */
        $queueRepository = $container->get(MessageQueueRepository::class);

        return new PublisherCommand($queueRepository);
    }
}
