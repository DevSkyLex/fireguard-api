<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Sla;

use DateTimeImmutable;

/**
 * Contract NonConformitySlaCandidate.
 *
 * Lightweight read model of an unresolved non-conformity as the SLA
 * escalation sweep sees it: enough to compute the breach (severity, age)
 * and to address the notification (organization, inspection).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitySlaCandidate
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the non-conformity identifier
   * @param string $inspectionId the owning inspection identifier
   * @param string $organizationId the owning organization identifier
   * @param string $severity the non-conformity severity value
   * @param DateTimeImmutable $createdAt the instant the non-conformity was opened
   */
  public function __construct(
    public string $id,
    public string $inspectionId,
    public string $organizationId,
    public string $severity,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
