<?php

namespace ZfrEbWorker\Cli;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZfrEbWorker\Publisher\QueuePublisherInterface;

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
     * @var QueuePublisherInterface
     */
    private $queuePublisher;

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @param QueuePublisherInterface $queuePublisher
     * @param SqsClient               $sqsClient
     */
    public function __construct(QueuePublisherInterface $queuePublisher, SqsClient $sqsClient)
    {
        $this->queuePublisher = $queuePublisher;
        $this->sqsClient      = $sqsClient;

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
                'Queue name to pull messages from'
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

        try {
            $queueUrl = $this->sqsClient->getQueueUrl(['QueueName' => $queueName])['QueueUrl'];
        } catch (SqsException $exception) {
            $output->writeln(sprintf(
                '<error>Impossible to retrieve URL for queue "%s". Reason: %s</error>',
                $queueName,
                $exception->getMessage()
            ));

            return;
        }

        $payload = [];
        parse_str($input->getOption('payload'), $payload);

        $this->queuePublisher->setQueue($queueName, $queueUrl);
        $this->queuePublisher->push($queueName, $name, $payload);
        $this->queuePublisher->flush();

        $output->writeln(sprintf(
            '<info>Message for "%s" has been added to the queue "%s" with the following payload: %s.</info>',
            $name,
            $queueName,
            json_encode($payload)
        ));
    }
}
