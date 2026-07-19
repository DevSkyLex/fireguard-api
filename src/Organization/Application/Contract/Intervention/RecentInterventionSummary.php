<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Intervention;

use DateTimeImmutable;

/**
 * Contract RecentInterventionSummary.
 *
 * Read model exposed outside the Intervention module, used to enrich the
 * organization dashboard's recent-interventions list without depending on
 * the Intervention module's Domain or Infrastructure layers.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecentInterventionSummary
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the intervention identifier
   * @param int $number the human-readable per-organization sequential number
   * @param string $name the intervention name
   * @param string $status the intervention status value
   * @param string $priority the intervention priority value
   * @param ?string $siteId the assigned facility identifier, when set
   * @param ?string $responsibleMemberId the assigned organization member identifier, when set
   * @param ?DateTimeImmutable $dueAt the due datetime, when set
   * @param DateTimeImmutable $updatedAt the last update datetime
   */
  public function __construct(
    public string $id,
    public int $number,
    public string $name,
    public string $status,
    public string $priority,
    public ?string $siteId,
    public ?string $responsibleMemberId,
    public ?DateTimeImmutable $dueAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
