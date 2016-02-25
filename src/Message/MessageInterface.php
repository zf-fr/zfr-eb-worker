<?php

namespace ZfrEbWorker\Message;

/**
 * @author Michaël Gallego
 */
interface MessageInterface
{
    /**
     * Get the message name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the message payload
     *
     * @return array
     */
    public function getPayload(): array;
}
