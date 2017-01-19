<?php

namespace ZfrEbWorker\Message;

/**
 * @author Michaël Gallego
 */
class Message implements FifoMessageInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $payload;

    /**
     * @var string
     */
    private $groupId;

    /**
     * @var string|null
     */
    private $deduplicationId;

    /**
     * @param string      $name
     * @param array       $payload
     * @param string|null $groupId
     * @param string|null $deduplicationId
     */
    public function __construct(string $name, array $payload, string $groupId = null, string $deduplicationId = null)
    {
        $this->name            = $name;
        $this->payload         = $payload;
        $this->deduplicationId = $deduplicationId;
        $this->groupId         = $groupId ?: bin2hex(random_bytes(64)); // Provide a default, random value
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return null|string
     */
    public function getDeduplicationId(): ?string
    {
        return $this->deduplicationId;
    }

    /**
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }
}
