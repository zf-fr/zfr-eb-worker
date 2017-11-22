<?php

namespace ZfrEbWorker\Cli;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Define a Symfony CLI command
 *
 * Usage:
 *
 *  php console.php eb-worker --queue=default --server=localhost
 *
 * It will automatically fetches message from the queue, and push them to POST "localhost/internal/worker" by populating
 * the request like Elastic Beanstalk does it natively
 *
 * @author Michaël Gallego
 */
class WorkerCommand extends Command
{
    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @param SqsClient  $sqsClient
     * @param HttpClient $httpClient
     */
    public function __construct(SqsClient $sqsClient, HttpClient $httpClient)
    {
        $this->sqsClient  = $sqsClient;
        $this->httpClient = $httpClient;

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
            ->setName('eb-worker')
            ->setDescription('Launch the local Elastic Beanstalk worker')
            ->addOption(
                'server',
                null,
                InputOption::VALUE_REQUIRED,
                'Server URL to which requests are redirected'
            )
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to add to the server URL',
                '/internal/worker'
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

        $uri = rtrim($input->getOption('server'), '/') . '/' . ltrim($input->getOption('path'), '/');

        while (true) {
            $messages = $this->sqsClient->receiveMessage([
                'QueueUrl'              => $queueUrl,
                'AttributeNames'        => ['All'],
                'MessageAttributeNames' => ['Name'],
                'MaxNumberOfMessages'   => 1,
                'WaitTimeSeconds'       => 20
            ]);

            if (!$messages->hasKey('Messages')) {
                continue;
            }

            $this->processMessage($messages['Messages'][0], $uri, $queueName, $queueUrl, $output);
        }
    }

    /**
     * Process a single message and push it to the server URL
     *
     * @link   http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html
     * @param  array           $message
     * @param  string          $uri
     * @param  string          $queueName
     * @param  string          $queueUrl
     * @param  OutputInterface $output
     * @return void
     */
    private function processMessage(
        array $message,
        string $uri,
        string $queueName,
        string $queueUrl,
        OutputInterface $output
    ) {
        $headers = [
            'User-Agent'                   => 'aws-sqsd/2.0',
            'Content-Type'                 => 'application/json',
            'X-Aws-Sqsd-Msgid'             => $message['MessageId'],
            'X-Aws-Sqsd-Queue'             => $queueName,
            'X-Aws-Sqsd-First-Received-At' => $message['Attributes']['ApproximateFirstReceiveTimestamp'],
            'X-Aws-Sqsd-Receive-Count'     => $message['Attributes']['ApproximateReceiveCount'],
            'X-Aws-Sqsd-Sender-Id'         => $message['Attributes']['SenderId'],
            'X-Aws-Sqsd-Attr-Name'         => (isset($message['MessageAttributes']['Name']['StringValue'])) ? $message['MessageAttributes']['Name']['StringValue'] : null;
        ];

        try {
            $response = $this->httpClient->post($uri, [
                'headers' => $headers,
                'json' => json_decode($message['Body'], true)
            ]);

            if ($response->getStatusCode() === 200) {
                $this->sqsClient->deleteMessage([
                    'QueueUrl'      => $queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle']
                ]);

                $output->writeln(sprintf(
                    '<info>Message "%s" has been processed and deleted from the queue "%s".</info>',
                    $message['MessageId'],
                    $queueName
                ));
            } else {
                $output->writeln(sprintf(
                    '<error>Message "%s" could not be processed and back-end returned error %s. Reason: %s</error>',
                    $message['MessageId'],
                    $response->getStatusCode(),
                    $response->getBody()
                ));
            }
        } catch (ClientException $e) {
            $output->writeln(sprintf(
                '<error>Message "%s" could not be processed and back-end returned error %s. Reason: %s</error>',
                $message['MessageId'],
                $e->getResponse()->getStatusCode(),
                $e->getResponse()->getBody()->getContents()
            ));
        }
    }
}
