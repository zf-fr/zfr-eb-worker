ZfrSqsWorker
============

ZfrSqsWorker is a simple abstraction around SQS, aims to simplify the creation of app in Elastic Beanstalk.

## Dependencies

* PHP 7.0+

## Installation

Installation of ZfrSqsWorker is only officially supported using Composer:

```sh
php composer.phar require 'zfr/zfr-sqs-worker:1.*'
```

## How Elastic Beanstalk work?

You can learn more about how Elastic Beanstalk worker/CRON work here: http://docs.aws.amazon.com/elasticbeanstalk/latest/dg/using-features-managing-env-tiers.html

Note that Elastic Beanstalk automatically delete the message from the queue if you return a 200. That's why this package does not
have any "deleteMessage".