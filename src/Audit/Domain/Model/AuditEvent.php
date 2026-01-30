<?php

declare(strict_types=1);

namespace Audit\Domain\Model;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Uuid;

/**
 * Model AuditEvent.
 *
 * Represents an immutable audit event
 * to be persisted in the audit log.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEvent
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuditEvent class.
   *
   * @since 1.0.0
   *
   * @param Uuid $id the audit event ID
   * @param string $action the audit action key
   * @param string $actorType the actor type (user, client, system, anonymous)
   * @param string|null $actorId the actor identifier (if any)
   * @param string|null $actorEmail the actor email (optional)
   * @param string|null $actorEmailHash the hashed actor email
   * @param string|null $subjectType the subject type (token, user, client, etc.)
   * @param string|null $subjectId the subject identifier
   * @param string|null $clientId the client identifier (if any)
   * @param string|null $tenantId the tenant identifier (if any)
   * @param string|null $ipAddress the client IP address (optional)
   * @param string|null $ipHash the hashed client IP address
   * @param string|null $userAgent the user agent string (optional)
   * @param array<string, mixed> $metadata the event metadata
   * @param DateTimeImmutable $occurredAt when the event occurred
   * @param DateTimeImmutable|null $recordedAt when the event was recorded
   * @param string|null $chainId the audit chain identifier
   * @param int|null $sequence the sequence in the audit chain
   * @param string|null $prevHash the previous event hash
   * @param string|null $eventHash the hash of the current event
   */
  public function __construct(
    public Uuid $id,
    public string $action,
    public string $actorType,
    public ?string $actorId,
    public ?string $actorEmail,
    public ?string $actorEmailHash,
    public ?string $subjectType,
    public ?string $subjectId,
    public ?string $clientId,
    public ?string $tenantId,
    public ?string $ipAddress,
    public ?string $ipHash,
    public ?string $userAgent,
    public array $metadata,
    public DateTimeImmutable $occurredAt,
    public ?DateTimeImmutable $recordedAt = null,
    public ?string $chainId = null,
    public ?int $sequence = null,
    public ?string $prevHash = null,
    public ?string $eventHash = null,
  ) {
  }
}
