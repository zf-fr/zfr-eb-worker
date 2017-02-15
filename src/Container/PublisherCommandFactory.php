<?php

namespace ZfrEbWorker\Container;

use Psr\Container\ContainerInterface;
use ZfrEbWorker\Cli\PublisherCommand;
use ZfrEbWorker\MessageQueue\InMemoryMessageQueueRepository;
use ZfrEbWorker\MessageQueue\MessageQueueRepositoryInterface;

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
        /** @var InMemoryMessageQueueRepository $queueRepository */
        $queueRepository = $container->get(MessageQueueRepositoryInterface::class);

        return new PublisherCommand($queueRepository);
    }
}
