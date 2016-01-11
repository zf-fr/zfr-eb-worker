ZfrEbWorker
============

[![Build Status](https://travis-ci.org/zf-fr/zfr-eb-worker.svg)](https://travis-ci.org/zf-fr/zfr-eb-worker)

ZfrEbWorker is a simple abstraction around SQS, aims to simplify the creation of app in Elastic Beanstalk.

## Dependencies

* PHP 7.0+

## Installation

Installation of ZfrEbWorker is only officially supported using Composer:

```sh
php composer.phar require 'zfr/zfr-eb-worker:2.*'
```

## How Elastic Beanstalk work?

You can learn more about how Elastic Beanstalk worker/CRON work here: http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html

Note that Elastic Beanstalk automatically delete the message from the queue if you return a 200. That's why this package does not
have any "deleteMessage".

## Usage

### Library configuration

First, make sure to configure the ZfrEbWorker library by adding this config:

```php
'zfr_eb_worker' => [
    'queues' => [
        'first_queue' => 'https://sqs.us-east-1.amazon.com/foo',
        'second_queue' => 'https://sqs.us-east-1.amazon.com/bar'
    ],

    'tasks' => [
        'send_campaign' => SendCampaignMiddleware::class,
        'process_image' => ProcessImageMiddleware::class
    ]
```

The `queues` is an associative array of queue name and queue URL hosted on AWS SQS, while `tasks` is an associative array that map
a task name to a specific middleware.

### Configuring Elastic Beanstalk

Then, you should configure your Elastic Beanstalk worker environment to push messages to "/internal/worker" URL (this is the
default URL configured if you use Zend Expressive). You could even add a pre-routing middleware to do additional security check
on this URL.

### Pushing message

You can push messages by injecting the `QueuePublisher` service into your classes. You need to first add one or more messages,
then flush the queue. When flushing, the library will make sure to do as few call as possible to SQS (using optimized SQS batch API),
and to multiple queues:

```php
$queuePublisher->push('default_queue', 'process_image', ['image_id' => 123]);
$queuePublisher->push('default_queue', 'process_image', ['image_id' => 456]);

// ...

$queuePublisher->flush();
```

The `push` method also accepts a fourth optional array parameters, where you can add specific info on a per-message basis. As of now,
those options are accepted:

* `delay_delay`: specify how many seconds before the message can be pulled from the first time by SQS. The maximum value is 900 (15 minutes).

Example usage:

```php
$queuePublisher->push('default_queue', 'process_image', ['image_id' => 123], ['delay_seconds' => 60]);
```

### Retrieving task info

ZfrEbWorker will automatically dispatch the incoming request to the middleware specified for the given task. The task information is
stored inside various request attributes, as shown below:

```php
class MyTaskMiddleware
{
    public function __invoke($request, $response, $out)
    {
        $queue       = $request->getAttribute('worker.matched_queue');
        $messageId   = $request->getAttribute('worker.message_id');
        $messageBody = $request->getAttribute('worker.message_body');
        $taskName    = $request->getAttribute('worker.task_name');
    }
}
```

### How to use periodic tasks?

Elastic Beanstalk also supports periodic tasks through the usage of `cron.yaml` file. However, this is actually easier as you can
specify a different URL on a task-basis. Therefore, you can dispatch to the URL of your choice and immediately be re-routed to the
correct middleware.