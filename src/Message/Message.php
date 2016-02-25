<?php

namespace ZfrEbWorker\Message;

/**
 * @author Michaël Gallego
 */
class Message implements MessageInterface
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
     * @param string $name
     * @param array  $payload
     */
    public function __construct(string $name, array $payload)
    {
        $this->name    = $name;
        $this->payload = $payload;
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
}