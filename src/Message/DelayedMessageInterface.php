<?php

namespace ZfrEbWorker\Message;

/**
 * @author Michaël Gallego
 */
interface DelayedMessageInterface extends MessageInterface
{
    /**
     * Get the delay (in seconds)
     *
     * @return int
     */
    public function getDelay(): int;
}
