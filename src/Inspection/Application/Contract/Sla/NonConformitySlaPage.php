<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Sla;

/**
 * Contract NonConformitySlaPage.
 *
 * One page of SLA escalation candidates — mirrors
 * `Intervention\Application\Contract\Reminder\InterventionReminderPage`.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitySlaPage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<NonConformitySlaCandidate> $items the candidates of this page
   */
  public function __construct(
    public array $items,
  ) {
  }
  // #endregion
}
