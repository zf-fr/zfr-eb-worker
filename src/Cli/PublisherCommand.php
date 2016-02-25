<?php

namespace ZfrEbWorker\Cli;

use Aws\Sqs\SqsClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZfrEbWorker\Message\Message;
use ZfrEbWorker\Queue\MessageQueueRepository;

/**
 * Define a Symfony CLI command
 *
 * Usage:
 *
 *  php console.php eb-publisher --payload='foo=bar&bar=baz' --queue=default --server=localhost
 *
 * It will automatically add a message using the expected structure of ZfrEbWorker
 *
 * @author Michaël Gallego
 */
class PublisherCommand extends Command
{
    /**
     * @var MessageQueueRepository
     */
    private $queueRepository;

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @param MessageQueueRepository $queuePublisher
     * @param SqsClient       $sqsClient
     */
    public function __construct(MessageQueueRepository $queuePublisher, SqsClient $sqsClient)
    {
        $this->queueRepository = $queuePublisher;
        $this->sqsClient       = $sqsClient;

        parent::__construct();
    }

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('eb-publisher')
            ->setDescription('Add a message into the Elastic Beanstalk queue')
            ->addOption(
                'payload',
                null,
                InputArgument::OPTIONAL,
                'Payload by using an HTML-style query (eg.: bar=baz&foo[bar]=baz)',
                ''
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Message name (eg.: user.created)'
            )
            ->addOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'Queue name to publish for'
            );
    }

    /**
     * Executes the current command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name      = $input->getOption('name');
        $queueName = $input->getOption('queue');

        $payload = [];
        parse_str($input->getOption('payload'), $payload);

        $queue = $this->queueRepository->getQueueByName($queueName);

        $queue->push(new Message($name, $payload));
        $queue->flush();

        $output->writeln(sprintf(
            '<info>Message for "%s" has been added to the queue "%s" with the following payload: %s.</info>',
            $name,
            $queueName,
            json_encode($payload)
        ));
    }
}
