<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationAuditEvents;

/**
 * UseCase OrganizationAuditEventResult.
 *
 * One organization-scoped audit ledger entry, reduced to the
 * fields an organization member is entitled to see. Actor email,
 * IP address, and the ledger chain internals are deliberately
 * absent: organization admins are a lesser-trust audience than
 * platform auditors, regardless of the PII settings that govern
 * the platform-level audit API. The narrowing itself is the Audit
 * module's invariant — see
 * {@see \Audit\Application\Contract\OrganizationAuditEntry}.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAuditEventResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * OrganizationAuditEventResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the audit event ID
   * @param string $action the audit action key
   * @param string $actorType the actor type (user, client, system, anonymous)
   * @param string|null $actorId the actor identifier
   * @param bool $actorIsOrganizationMember whether the actor holds a membership in this
   *                                        organization, and may therefore be named to it
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
    public bool $actorIsOrganizationMember,
    public ?string $subjectType,
    public ?string $subjectId,
    public array $metadata,
    public string $occurredAt,
    public string $recordedAt,
  ) {
  }
  // #endregion
}
