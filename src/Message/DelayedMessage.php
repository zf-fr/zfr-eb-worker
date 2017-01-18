<?php

namespace ZfrEbWorker\Message;

/**
 * @author Michaël Gallego
 */
class DelayedMessage extends Message implements DelayedMessageInterface
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
