<?php

namespace ZfrEbWorker\Message;

use ZfrEbWorker\Exception\RuntimeException;

/**
 * @author Michaël Gallego
 */
class DelayedMessage extends Message
{
    /**
     * @var int
     */
    private $delay;

    /**
     * @param string $name
     * @param array  $payload
     * @param int    $delay
     */
    public function __construct(string $name, array $payload, int $delay)
    {
        if ($delay > (15 * 60)) {
            throw new RuntimeException(sprintf(
                'SQS only support delayed message for up to 900 seconds (15 minutes), "%s" given',
                $delay
            ));
        }

        parent::__construct($name, $payload);

        $this->delay = $delay;
    }

    /**
     * {@inheritDoc}
     */
    public function getDelay(): int
    {
        return $this->delay;
    }
}
