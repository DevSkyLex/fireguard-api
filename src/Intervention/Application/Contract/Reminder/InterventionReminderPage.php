<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Reminder;

/**
 * Contract InterventionReminderPage.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionReminderPage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<InterventionReminderCandidate> $items
   */
  public function __construct(
    public array $items,
  ) {
  }
  // #endregion
}
