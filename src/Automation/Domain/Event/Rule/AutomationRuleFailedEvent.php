<?php

declare(strict_types=1);

namespace Automation\Domain\Event\Rule;

use DateTimeImmutable;

/**
 * Event AutomationRuleFailedEvent.
 *
 * Raised by `ExecuteAutomationRuleHandler` when an automation rule's action
 * fails (e.g. the corrective intervention draft could not be created). The
 * run row already guards against duplicate retries producing duplicate
 * drafts, so this event is purely informational — no rethrow. The acting
 * principal is always the system. Recorded in the audit ledger as
 * `automation.rule_failed`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AutomationRuleFailedEvent
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
   * @param string $error the failure reason
   */
  public function __construct(
    public string $ruleKey,
    public string $organizationId,
    public string $subjectId,
    public string $error,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
