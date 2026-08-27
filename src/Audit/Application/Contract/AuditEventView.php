<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

use Shared\Application\Message\ResultMessage;

/**
 * Result AuditEventView.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEventView implements ResultMessage
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuditEventView class.
   *
   * @since 1.0.0
   *
   * @param string $id the audit event ID
   * @param string $action the audit action key
   * @param string $actorType the actor type
   * @param string|null $actorId the actor identifier
   * @param string|null $actorEmail the actor email (optional)
   * @param string|null $actorEmailHash the actor email hash
   * @param string|null $subjectType the subject type
   * @param string|null $subjectId the subject identifier
   * @param string|null $clientId the client identifier
   * @param string|null $tenantId the tenant identifier
   * @param string|null $ipAddress the client IP address (optional)
   * @param string|null $ipHash the client IP hash
   * @param string|null $userAgent the user agent string (optional)
   * @param array<string, mixed> $metadata the metadata payload
   * @param string $occurredAt ISO 8601 occurrence timestamp
   * @param string $recordedAt ISO 8601 recorded timestamp
   * @param string $chainId audit chain identifier
   * @param int $sequence audit sequence number
   * @param string|null $prevHash previous hash in chain
   * @param string $eventHash event hash
   * @param string|null $organizationId the organization identifier
   */
  public function __construct(
    public string $id,
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
    public string $occurredAt,
    public string $recordedAt,
    public string $chainId,
    public int $sequence,
    public ?string $prevHash,
    public string $eventHash,
    public ?string $organizationId = null,
  ) {
  }
}
