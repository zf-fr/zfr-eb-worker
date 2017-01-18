ZfrEbWorker
============

[![Build Status](https://travis-ci.org/zf-fr/zfr-eb-worker.svg)](https://travis-ci.org/zf-fr/zfr-eb-worker)

ZfrEbWorker is a simple abstraction around SQS, aims to simplify the creation of app in Elastic Beanstalk.

## Dependencies

* PHP 7.0+

## Installation

Installation of ZfrEbWorker is only officially supported using Composer:

```sh
php composer.phar require 'zfr/zfr-eb-worker:6.*'
```

## How Elastic Beanstalk work?

You can learn more about how Elastic Beanstalk worker/CRON work here: http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html

Note that Elastic Beanstalk automatically delete the message from the queue if you return a 200. That's why this package does not
have any "deleteMessage".

## Usage

### Library configuration

#### AWS configuration

ZfrEbWorker expects that you registers the `Aws\Sdk` class to the container of your choice. You are free to configure the SDK the way
you prefer, so ZfrEbWorker does not come with a default factory for that.

As an example, here is a simple `ContainerInterop` compatible factory:

```php
<?php

use Aws\Sdk as AwsSdk;
use Interop\Container\ContainerInterface;
use RuntimeException;

class AwsSdkFactory
{
    /**
     * @param  ContainerInterface $container
     * @return AwsSdk
     */
    public function __invoke(ContainerInterface $container): AwsSdk
    {
        $config = $container->get('config');

        if (!isset($config['aws'])) {
            throw new RuntimeException('Key "aws" is missing');
        }

        return new AwsSdk($config['aws']);
    }
}
```

Then register your factory (this example is using the Zend\ServiceManager style config):

```php
use Aws\Sdk as AwsSdk;

return [
    'dependencies' => [
        'factories' => [
            AwsSdk::class => AwsSdkFactory::class
        ]
    ]
];
```

Finally, modify your configuration to add the `aws` key to your config file (then, follows the official AWS SDK documentation
to know all the possible keys):

```php
return [
    'aws' => [
        'region'      => 'us-east-1', // Replace by your region
        'Sqs'         => ['version' => '2012-11-05'], // Add all your other services
        'credentials' => [
            'key'    => 'YOUR_USER_KEY',
            'secret' => 'YOUR_SECRET_KEY'
        ]
    ]
];
```

#### Worker configuration

First, make sure to configure the ZfrEbWorker library by adding this config:

```php
'zfr_eb_worker' => [
    'queues' => [
        'first_queue'  => 'https://sqs.us-east-1.amazon.com/foo',
        'second_queue' => 'https://sqs.us-east-1.amazon.com/bar'
    ],

    'messages' => [
        'project.created' => SendCampaignListener::class,
        'image.saved'     => ProcessImageListener::class
    ]
```

The `queues` is an associative array of queue name and queue URL hosted on AWS SQS, while `messages` is an associative array that map
a message name to a specific listeners (each listener is just a standard middleware).

You can also attach multiple listeners to a given message, hence allowing to do different actions based on a message:

```php
'zfr_eb_worker' => [
    'messages' => [
        'image.saved' => [
            OptimizeImageListener::class,
            UploadImageListener::class
        ]
    ]
```

#### Registering WorkerMiddleware
You should register the `WorkerMiddleware` in your router to respond the "/internal/worker" path.
This middleware consumes the messages sent by Elastic Beanstalk worker environment and routes to the mapped listener. For example, in Zend Expressive:

```php
use ZfrEbWorker\Middleware\WorkerMiddleware;

$app->post('/internal/worker', WorkerMiddleware::class);
```

### Configuring Elastic Beanstalk

Then, you should configure your Elastic Beanstalk worker environment to push messages to "/internal/worker" URL (this is the
default URL configured if you use Zend Expressive). By default, ZfrEbWorker do additional security checks to ensure that the request
is coming from localhost (as the daemon is installed on EC2 instances directly and push the messages locally):

### Pushing message

You can push messages by injecting a `MessageQueueInterface` object into your classes.

You can create a queue easily, pre-configured, by using a `MessageQueueRepositoryInterface` instance. For instance, assuming 
the following config:

```php
'zfr_eb_worker' => [
    'queues' => [
        'first_queue'  => 'https://sqs.us-east-1.amazon.com/foo'
    ]
]
```

You can use the repository to create the queue, configured with the URL:

```php
$queue = $queueRepository->getMessageQueue('first_queue');
```

Once you have a configured queue, you can add one or more messages, then flush the queue. When flushing, the
library will make sure to do as few call as possible to SQS (using optimized SQS batch API), and to multiple queues:

```php
$queue->push(new Message('image.saved', ['image_id' => 123]));
$queue->push(new Message('imave.saved', ['image_id' => 456]));

// ...

$queue->flush();
```

The `push` method accepts as a first argument a `MessageInterface` object, which is a thin wrapper for both a message
name and payload. ZfrEbWorker provides a default `Message` class.

You can also push delayed message (up to 600 seconds) by using the specialized DelayedMessage class:

Example usage:

```php
$queue->push(new DelayedMessage('image.saved', ['image_id' => 123], 60));
```

> Note: if you are using a FIFO queue, this won't have any effect. On FIFO queue, delay can only be applied globally for a queue.

### FIFO queues

Starting from version 6, ZfrEbWorker supports FIFO queues. You can either push a standard message (in which case ZfrEbWorker will provide
a default group ID), but you can also create a `FifoMessage` (or create your custom messages by implementing `FifoMessageInterface`) which allows
you to provide a custom group ID and deduplication ID:

```php
$message = new FifoMessage('image.saved', ['image_id' => 123], 'group_id', 'deduplication_id');
$queue->push($message);
```

### Retrieving message info

ZfrEbWorker will automatically dispatch the incoming request to the middleware specified for the given event. The message information is
stored inside various request attributes, as shown below:

```php
use ZfrEbWorker\Middleware\WorkerMiddleware;

class MyEventMiddleware
{
    public function __invoke($request, $response, $out)
    {
        $queue          = $request->getAttribute(WorkerMiddleware::MATCHED_QUEUE_ATTRIBUTE);
        $messageId      = $request->getAttribute(WorkerMiddleware::MESSAGE_ID_ATTRIBUTE);
        $messagePayload = $request->getAttribute(WorkerMiddleware::MESSAGE_PAYLOAD_ATTRIBUTE);
        $name           = $request->getAttribute(WorkerMiddleware::MESSAGE_NAME_ATTRIBUTE);
    }
}
```

> Note: for a periodic task, only the `Middleware::MESSAGE_NAME_ATTRIBUTE` is available.

### How to silently ignore some message?

When ZfrEbWorker don't find a mapped middleware to handle a message, it throws a `RuntimeException`, which makes Elastic
Beanstalk retry the message again later. However if you don't want to handle a specific message and don't want Elastic
Beanstalk to retry it later, you should map SilentFailingListener to the message, like that:

```php
'zfr_eb_worker' => [
    'messages' => [
        'user.updated' => ZfrEbWorker\Listener\SilentFailingListener::class,
    ]
```

### How to use periodic tasks?

Elastic Beanstalk also supports periodic tasks through the usage of `cron.yaml` file ([more info](http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html#worker-periodictasks)).
ZfrEbWorker supports this use case in the same, unified way.

Simply redirect all your periodic tasks to the same "/internal/worker" route, and make sure that the task name you use is part of your config. For instance,
here is a task called "image.backup" that will run every 12 hours:

```yaml
version: 1
cron:
  - name: "image.backup"
    url: "/internal/worker"
    schedule: "0 */12 * * *"
```

Then, in your ZfrEbWorker config, just configure it like any other messages:

```php
'zfr_eb_worker' => [
    'messages' => [
        'image.backup' => ImageBackupListener::class,
    ]
```

## CLI commands

Starting from version 3.3, ZfrEbWorker comes with Symfony CLI commands that allows:
 
* to easily push messages into a queue that respect the ZfrEbWorker format.
* to emulate the usage of native Elastic Beanstalk worker to fetch messages and execute them.

> This local worker is only meant to be used in development. In production, you should use the native Elastic Beanstalk worker, which
is much faster (retrieves up to 10 messages in one SQS call) and is built-in into the Elastic Beanstalk AMI (it is monitored...).

### Setup

Before using those CLI commands, there are some things you need to setup, as described in following sections.

#### Add the dependencies

Make sure that you add those two dependencies in your project (typically, in the `require-dev` section of your `composer.json` file):

```json
{
  "require-dev": {
    "symfony/console": "^3.0",
    "guzzlehttp/guzzle": "^6.0"
  }
}
```

#### Adding a console entry point

ZfrEbWorker adds the `WorkerCommand` and `PublisherCommand` Symfony CLI command into the `console` top-key of the config. If you are using this library
with a framework that already uses Symfony CLI, just add the `ZfrEbWorker\Cli\WorkerCommand` and/or `ZfrEbWorker\Cli\PublisherCommand` commands.

If you are using Zend\Expressive, here is a sample file (call it `console.php` for instance) you can add into the `public` folder, 
alongside your `index.php` file:

```php
use Symfony\Component\Console\Application;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

/** @var \Interop\Container\ContainerInterface $container */
$container = require 'config/container.php';

$application = new Application('Application console');

$commands = $container->get('config')['console']['commands'];

foreach ($commands as $command) {
    $application->add($container->get($command));
}

$application->run();
```

#### Add IAM permissions

In order to allow the local worker to work, you'll need to add the `sqs:GetQueueUrl`, `sqs:ReceiveMessage`, `sqs:DeleteMessage` and
`sqs:SendMessage` permissions to the IAM user you are using locally.

> For security reasons, we recommend you to have production and development queues, so that your development IAM user only have access to the
development queue and cannot mess with the production queue.

### PublisherCommand

This command allows to easily add messages into an Elastic Beanstalk worker.

Use the following command: `php console.php eb-publisher --payload="foo=bar&bar=baz" --queue=my-queue --name=user.created`

The `payload` key supports an HTML-like query param, so if you want to add the following JSON:

```json
{
  "user": {
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

You can use the following payload: `--payload='user[first_name]=John&user[last_name]=Doe'`

### WorkerCommand

This command allows to simulate the native Elastic Beanstalk worker.

You can now write the command `php console.php eb-worker --server=http://localhost --queue=my-queue`. This code will automatically poll
the queue called `my-queue`, and push messages to the URL indicated by the `server` option with the `/internal/worker` path added (as this
is the default ZfrEbWorker configuration).

Therefore, in this example, the local worker will make a POST request to `http://localhost/internal/worker` whenever a new message is
added. The local worker behaves exactly the same way the native Elastic Beanstalk worker does, and adds all the same HTTP headers.
