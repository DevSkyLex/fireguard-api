<?php

declare(strict_types=1);

namespace Compliance\Domain\Event;

use DateTimeImmutable;

/**
 * Event SafetyRegisterExportedEvent.
 *
 * Raised each time a regulatory "registre de sécurité" PDF is exported,
 * whether for a whole organization or a single facility. Recorded in the
 * audit ledger as `compliance.register_exported` (actor = exporting user,
 * subject = organization/facility, payload = scope + plan + generatedAt),
 * satisfying the "who pulled the safety register" regulatory need without a
 * dedicated export-log table.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SafetyRegisterExportedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SafetyRegisterExportedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId the facility identifier, or null for an organization-wide export
   * @param string $actorUserId the exporting user identifier
   * @param string $planKey the organization's plan key at export time
   * @param string $scope `organization` or `facility`
   * @param string $generatedAt ISO 8601 datetime the exported register snapshot was generated at
   */
  public function __construct(
    public string $organizationId,
    public ?string $facilityId,
    public string $actorUserId,
    public string $planKey,
    public string $scope,
    public string $generatedAt,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
