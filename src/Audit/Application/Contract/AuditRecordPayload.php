<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

use DateTimeImmutable;

/**
 * Class AuditRecordPayload
 *
 * Carries the sanitized snapshot written to the durable request envelope. Producers use AuditRecordRequest rather than constructing ledger payloads.
 *
 * @category Contract
 */
final readonly class AuditRecordPayload
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Freezes privacy policy and attribution before enqueue, preserving the original hash-chain payload on replay.
   *
   * @access public
   *
   * @param string $action stable audit action
   * @param string $actorType resolved actor kind
   * @param ?string $actorId resolved principal identifier
   * @param ?string $actorEmail governed actor email
   * @param ?string $actorEmailHash keyed actor email hash
   * @param ?string $subjectType subject kind
   * @param ?string $subjectId subject identifier
   * @param ?string $clientId client scope
   * @param ?string $tenantId tenant scope
   * @param ?string $organizationId organization scope
   * @param ?string $ipAddress governed address
   * @param ?string $ipHash keyed address hash
   * @param ?string $userAgent request user agent
   * @param array<string, mixed> $metadata governed owner facts
   * @param DateTimeImmutable $occurredAt original occurrence time
   *
   * @return void
   */
  public function __construct(
    public string $action,
    public string $actorType,
    public ?string $actorId,
    public ?string $actorEmail,
    public ?string $actorEmailHash,
    public ?string $subjectType,
    public ?string $subjectId,
    public ?string $clientId,
    public ?string $tenantId,
    public ?string $organizationId,
    public ?string $ipAddress,
    public ?string $ipHash,
    public ?string $userAgent,
    public array $metadata,
    public DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion
}
