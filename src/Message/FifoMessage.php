<?php

namespace ZfrEbWorker\Message;

/**
 * @author Michaël Gallego
 */
class FifoMessage extends Message implements FifoMessageInterface
{
    /**
     * @var string|null
     */
    private $deduplicationId;

    /**
     * @var string
     */
    private $groupId;

    /**
     * @param string      $name
     * @param array       $payload
     * @param string|null $groupId
     * @param string|null $deduplicationId
     */
    public function __construct(string $name, array $payload, string $groupId = null, string $deduplicationId = null)
    {
        parent::__construct($name, $payload);
        $this->deduplicationId = $deduplicationId;
        $this->groupId         = $groupId ?: bin2hex(random_bytes(64)); // Provide a default, random value;
    }

    /**
     * {@inheritDoc}
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeduplicationId(): ?string
    {
        return $this->deduplicationId;
    }
}
