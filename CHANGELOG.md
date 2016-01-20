# 3.0.0

* Naming was (again, sorry...) changed to better reflect common usage (domain events). "task_name" was renamed to "name", while
"attributes" was renamed "payload"

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