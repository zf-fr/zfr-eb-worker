# 6.0.0

* Dependency has been bumped to PHP 7.1.
* A new `DelayedMessageInterface` has now been added.
* Support for FIFO queues has been added.
* [BC break] Middlewares are now compatible with latest HTTP middlewares interfaces.
* [BC break] `LocalhostCheckerMiddleware` has been removed. Now, all the security checks are done within the `WorkerMiddleware`. Especially, in addition of
the previous security check that enforced the request to come from localhost, we now also check the user agent to verify the request originated from Amazon.
* [BC break] Worker route is not added automatically anymore. You will need to manually attach the middleware to the path of your choice.
* [BC break] The structure of messages has been modified. Previously, a message was encoded this way:

```json
{
  "name": "message_name",
  "payload": {
    "id": "123",
    "other": "value"
  }
}
```

Now, message body only contain the message content itself:

```json
{
  "id": "123",
  "other": "value"
}
```

This allows for simpler messages, and prevent parsing the body to get the message name. The message name is now added through a "message attribute"
called "Name".

When a message is pushed by EC2 instances, the SQSD daemon automatically creates headers with the message attributes. In our case, the daemon automatically
adds a header called `X-Aws-Sqsd-Attr-Name` that contains the message name, which is read by the worker to redirect to the proper listeners.

This has two major consequences:

1) Messages created using an older version won't be read by the newer version of the worker. This means that you'll need to deploy both your producer and
consumer applications at the same time to minimize the problems. Once your consumer has been deployed, it may still received some messages coming in the old
format (those created before the deployment). We recommend you to setup a dead letter queue to be able to gracefully retry your messages after.

2) Because of the use of SQS attributes, you cannot any longer use this version with old Elastic Beanstalk environments. We therefore recommend you to update
your worker environments to a 2016 IAM or later so that the daemon properly redirects the attributes.

# 5.3.0

* Add support for periodic tasks

# 5.2.0

* Always add a `X-Handled-By` to the response at the end of the worker pipeline. This allows to make sure that the original response is modified, as
some middleware frameworks like `Zend\Expressive` returns a 404 if the response has not been modified at all. [#31]
* Worker middleware can be simplified to only have `ServerRequestInterface` as unique parameter. [#30]

# 5.1.2

* Fixed a bug that prevented worker middleware to work properly when localhost checker middleware was used

# 5.1.1

* Adds support for detecting localhost requests coming from Docker environment

# 5.1.0

* ZfrEbWorker adds a new security improvement by restricting the internal worker to localhost only.

# 5.0.0

* Now uses [`Zend\ComponentInstaller`](https://zendframework.github.io/zend-component-installer/) for config

# 4.0.2

* Add missing factory for `SilentFailingListener`.

# 4.0.1

* Message body is now encoded in RFC4627-compliant JSON by using some encoding flags.

# 4.0.0

* `QueuePublisherInterface` has been removed and replaced by a single `MessageQueueInterface`, tied to a single queue.
* A new `MessageQueueRepositoryInterface` has been introduced to allow fetching message queues by name.
* Added a new `Message` and `DelayedMessage` abstraction for easier creation of messages.

# 3.3.0

* Add a new `SilentFailingListener` class that you can use if you don't want to handle a specific message.
* Add a CLI command that simulate locally the Elastic Beanstalk worker. This new worker should only be used for
development, never in production.
* Add a CLI command that allows to easily push messages to a given queue which respects the ZfrEbWorker structure.

# 3.2.0

* Deprecate the `AppConfig` configuration provider, use `ModuleConfig` instead (for better reflect distinction between app and
reusable code).

# 3.1.0

* Add new constants for request attributes.
* Add a new optional argument to `flush` method that allows to flush the queue asynchronously (ideal when you want to reduce
latency).
* Allow to set multiple middlewares for a given message (see documentation).

# 3.0.0

* Naming was (again, sorry...) changed to better reflect usage. Especially: 
    * In the message added to SQS, `task_name` was renamed `name`, and `attributes` was renamed `payload`.
    * In the configuration, config key `tasks` was renamed `messages`
    * In the request, message payload is now retrieved using `worker.message_payload` key, while message name is retrieved
    using `worker.message_name` key.

# 2.1.0

* Add a new `setQueue` method to `QueuePublisherInterface` to add new queues at runtime.

# 2.0.4

* Use `require_once` instead of `include_once` for `AppConfig`.

# 2.0.3

* Fix the `AppConfig` path.

# 2.0.2

* Add a `QueuePublisherInterface` key as alias, that maps to the concrete implementation for cleaner re-usability.

# 2.0.1

* Add the attribute `worker.task_name` to the request attribute

# 2.0.0

* `jobs` were renamed `tasks` to be on-par with Elastic Beanstalk worker terminology.

# 1.1.0

* [BC] `WorkerMiddleware` has been moved to its own namespace. You should not have any problem unless you were
explicitly extending the middleware.

# 1.0.4

* Update PHPUnit dependency for better PHP7 support

# 1.0.3

* Fix directory for Container tests

# 1.0.2

* Ensure that messages are not duplicated by clearing the message on flush

# 1.0.1

* Fix typo in exception error message

# 1.0.0

* Initial release