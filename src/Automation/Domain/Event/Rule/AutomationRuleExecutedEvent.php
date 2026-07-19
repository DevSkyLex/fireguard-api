<?php

declare(strict_types=1);

namespace Automation\Domain\Event\Rule;

use DateTimeImmutable;

/**
 * Event AutomationRuleExecutedEvent.
 *
 * Raised by `ExecuteAutomationRuleHandler` when an automation rule
 * successfully produces its action (e.g. a corrective intervention draft).
 * The acting principal is always the system. Recorded in the audit ledger as
 * `automation.rule_executed`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AutomationRuleExecutedEvent
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
   * @since 1.0.0
   *
   * @param string $ruleKey the automation rule key
   * @param string $organizationId the organization identifier
   * @param string $subjectId the subject identifier the rule ran against
   * @param string $interventionId the created corrective intervention identifier
   */
  public function __construct(
    public string $ruleKey,
    public string $organizationId,
    public string $subjectId,
    public string $interventionId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
