<?php

namespace ZfrEbWorker\Container;

use Aws\Sdk as AwsSdk;
use GuzzleHttp\Client as HttpClient;
use Interop\Container\ContainerInterface;
use ZfrEbWorker\Cli\WorkerCommand;

/**
 * @author Michaël Gallego
 */
class WorkerCommandFactory
{
    /**
     * @param  ContainerInterface $container
     * @return WorkerCommand
     */
    public function __invoke(ContainerInterface $container): WorkerCommand
    {
        /** @var AwsSdk $awsSdk */
        $awsSdk    = $container->get(AwsSdk::class);
        $sqsClient = $awsSdk->createSqs();

        return new WorkerCommand($sqsClient, new HttpClient());
    }
}
