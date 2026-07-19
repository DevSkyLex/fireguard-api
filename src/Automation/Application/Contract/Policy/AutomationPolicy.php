<?php

declare(strict_types=1);

namespace Automation\Application\Contract\Policy;

/**
 * Contract AutomationPolicy.
 *
 * An organization's effective automation policy: whether each rule is
 * enabled, plus the resolved values the rules' actions need (e.g. the
 * non-conformity resolution SLA per severity, used to derive a corrective
 * intervention's due date).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AutomationPolicy
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $autoCreateInterventionOnCriticalNc whether a critical non-conformity auto-creates a corrective intervention draft
   * @param array<string, int> $nonConformitySlaDays the effective non-conformity resolution SLA, in days, per severity
   */
  public function __construct(
    public bool $autoCreateInterventionOnCriticalNc,
    public array $nonConformitySlaDays,
  ) {
  }
  // #endregion
}
