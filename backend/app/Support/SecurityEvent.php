<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class SecurityEvent implements JsonSerializable
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $event,
        public string $action,
        public string $result,
        public ?int $actorId = null,
        public ?string $resourceType = null,
        public int|string|null $resourceId = null,
        public ?string $ipAddress = null,
        public ?string $correlationId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        foreach ([$event, $action, $result] as $requiredValue) {
            if (trim($requiredValue) === '') {
                throw new InvalidArgumentException('Event, action and result must not be empty.');
            }
        }

        $this->occurredAt = ($occurredAt ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));
    }

    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'event' => $this->event,
            'action' => $this->action,
            'result' => $this->result,
            'actor_id' => $this->actorId,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'occurred_at' => $this->occurredAt->format('Y-m-d\TH:i:s.u\Z'),
            'ip_address' => $this->ipAddress,
            'correlation_id' => $this->correlationId,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
