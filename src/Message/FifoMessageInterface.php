<?php

namespace ZfrEbWorker\Message;

/**
 * @author Michaël Gallego
 */
interface FifoMessageInterface extends MessageInterface
{
    /**
     * Get the message group ID
     *
     * This can be used to make sure that all messages within a given group are treated in a FIFO manner
     *
     * @return string
     */
    public function getGroupId(): string;

    /**
     * Get the message deduplication ID
     *
     * This can be used to override the default content-based deduplication strategy, or if you want to override
     * the value used to decide if a message is a duplicate or not. If you want to keep the default strategy (only
     * if your queue has content-based deduplication strategy), then returns null.
     *
     * @return string|null
     */
    public function getDeduplicationId(): ?string;
}
