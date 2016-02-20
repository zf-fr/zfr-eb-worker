ZfrEbWorker
============

[![Build Status](https://travis-ci.org/zf-fr/zfr-eb-worker.svg)](https://travis-ci.org/zf-fr/zfr-eb-worker)

ZfrEbWorker is a simple abstraction around SQS, aims to simplify the creation of app in Elastic Beanstalk.

## Dependencies

* PHP 7.0+

## Installation

Installation of ZfrEbWorker is only officially supported using Composer:

```sh
php composer.phar require 'zfr/zfr-eb-worker:3.*'
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

#### Worker configuration

First, make sure to configure the ZfrEbWorker library by adding this config:

```php
'zfr_eb_worker' => [
    'queues' => [
        'first_queue' => 'https://sqs.us-east-1.amazon.com/foo',
        'second_queue' => 'https://sqs.us-east-1.amazon.com/bar'
    ],

    'messages' => [
        'project.created' => SendCampaignMiddleware::class,
        'image.saved'     => ProcessImageMiddleware::class
    ]
```

The `queues` is an associative array of queue name and queue URL hosted on AWS SQS, while `messages` is an associative array that map
a message name to a specific middleware.

You can also attach multiple middlewares to a given message, hence allowing to do different actions based on a message:

```php
'zfr_eb_worker' => [
    'messages' => [
        'image.saved' => [
            OptimizeImageMiddleware::class,
            UploadImageMiddleware::class
        ]
    ]
```

### Configuring Elastic Beanstalk

Then, you should configure your Elastic Beanstalk worker environment to push messages to "/internal/worker" URL (this is the
default URL configured if you use Zend Expressive). You could even add a pre-routing middleware to do additional security check
on this URL.

### Pushing message

You can push messages by injecting the `QueuePublisher` service into your classes. You need to first add one or more messages,
then flush the queue. When flushing, the library will make sure to do as few call as possible to SQS (using optimized SQS batch API),
and to multiple queues:

```php
$queuePublisher->push('default_queue', 'image.saved', ['image_id' => 123]);
$queuePublisher->push('default_queue', 'imave.saved', ['image_id' => 456]);

// ...

$queuePublisher->flush();
```

The `push` method also accepts a fourth optional array parameters, where you can add specific info on a per-message basis. As of now,
those options are accepted:

* `delay_delay`: specify how many seconds before the message can be pulled from the first time by SQS. The maximum value is 900 (15 minutes).

Example usage:

```php
$queuePublisher->push('default_queue', 'image.saved', ['image_id' => 123], ['delay_seconds' => 60]);
```

Your worker then could optimize the image as a response of this event.

### Retrieving message info

ZfrEbWorker will automatically dispatch the incoming request to the middleware specified for the given event. The message information is
stored inside various request attributes, as shown below:

```php
class MyEventMiddleware
{
    public function __invoke($request, $response, $out)
    {
        $queue          = $request->getAttribute('worker.matched_queue');
        $messageId      = $request->getAttribute('worker.message_id');
        $messagePayload = $request->getAttribute('worker.message_payload');
        $name           = $request->getAttribute('worker.message_name');
    }
}
```

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

Elastic Beanstalk also supports periodic tasks through the usage of `cron.yaml` file. However, this is actually easier as you can
specify a different URL on a task-basis. Therefore, you can dispatch to the URL of your choice and immediately be re-routed to the
correct middleware.

### Using the local worker

Starting from version 3.3, ZfrEbWorker comes with a Symfony CLI command that allows to emulate the usage of native Elastic Beanstalk
worker. In order to use it, you must do various things:

> This local worker is only meant to be used in development. In production, you should use the native Elastic Beanstalk worker, which
is much faster (retrieves up to 10 messages in one SQS call) and is built-in into the Elastic Beanstalk AMI (it is monitored...).

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

ZfrEbWorker adds the `WorkerCommand` Symfony CLI command into the `console` top-key of the config. If you are using this library
with a framework that already uses Symfony CLI, just add the `ZfrEbWorker\Cli\WorkerCommand` command.

If you are using Zend\Expressive, here is a sample file (call it `console.php` for instance) you can add into the `public` folder, 
alongside your `index.php` file:

```php
require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Zend\ServiceManager\ServiceManager;
use ZfrEbWorker\ModuleConfig;

$config = (new ModuleConfig())->__invoke();

// Build container
$container = new ServiceManager($config['dependencies']);

// Inject config
$container->setService('config', $config);

$application = new Application('Application console');

$commands = $container->get('config')['console']['commands'];

foreach ($commands as $command) {
    $application->add($container->get($command));
}

$application->run();
```

#### Add IAM permissions

In order to allow the local worker to work, you'll need to add the `sqs:GetQueueUrl`, `sqs:ReceiveMessage` and `sqs:DeleteMessage` permissions
to the IAM user you are using locally.

#### Use the tool

You can now write the command `php console.php eb-worker --server=http://localhost --queue=my-queue`. This code will automatically poll
the queue called `my-queue`, and push messages to the URL indicated by the `server` option with the `/internal/worker` path added (as this
is the default ZfrEbWorker configuration).

Therefore, in this example, the local worker will make a POST request to `http://localhost/internal/worker` whenever a new message is
added. The local worker behaves exactly the same way the native Elastic Beanstalk worker does, and adds all the same HTTP headers.