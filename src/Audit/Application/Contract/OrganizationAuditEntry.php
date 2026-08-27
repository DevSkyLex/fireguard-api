<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

/**
 * Contract OrganizationAuditEntry.
 *
 * The organization-facing projection of one ledger row, and the ONLY shape
 * {@see \Audit\Application\Port\Inbound\OrganizationAuditFeedPort} publishes.
 * It is deliberately narrower than {@see AuditEventView}: actor email (plain
 * or hashed), IP address/hash, user agent, client/tenant identifiers and the
 * chain internals (chain id, sequence, prev/event hash) are absent by
 * construction, not stripped downstream — an organization admin is a
 * lesser-trust audience than a platform auditor, whatever
 * `SECURITY_LOG_INCLUDE_PII` says.
 *
 * `$metadata` has already been through
 * {@see \Audit\Application\Service\OrganizationAuditMetadataProjection}, so a
 * consumer never sees a raw producer payload.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAuditEntry
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationAuditEntry class.
   *
   * @since 1.0.0
   *
   * @param string $id the audit event identifier
   * @param string $action the audit action key
   * @param string $actorType the actor type (user, client, system, anonymous)
   * @param string|null $actorId the actor identifier
   * @param string|null $subjectType the audited subject type
   * @param string|null $subjectId the audited subject identifier
   * @param array<string, mixed> $metadata the projected metadata payload
   * @param string $occurredAt ISO 8601 occurrence timestamp
   * @param string $recordedAt ISO 8601 recorded timestamp
   */
  public function __construct(
    public string $id,
    public string $action,
    public string $actorType,
    public ?string $actorId,
    public ?string $subjectType,
    public ?string $subjectId,
    public array $metadata,
    public string $occurredAt,
    public string $recordedAt,
  ) {
  }
  // #endregion
}
