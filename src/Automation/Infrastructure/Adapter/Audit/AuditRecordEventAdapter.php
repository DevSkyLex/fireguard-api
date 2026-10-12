<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Adapter\Audit;

use Audit\Application\Contract\AuditRecordRequest;
use Audit\Application\Port\Inbound\AuditRecordingPort;
use Automation\Application\Contract\Event\Rule\{AutomationRuleExecutedEvent, AutomationRuleFailedEvent};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class AuditRecordEventAdapter
 *
 * Projects Automation-owned event facts through the published Audit recording capability. Audit owns context, privacy and durable ledger delivery.
 *
 * @category Adapter
 */
final readonly class AuditRecordEventAdapter implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives the published durable recording seam.
   *
   * @access public
   *
   * @param AuditRecordingPort $audit generic Audit write capability
   *
   * @return void
   */
  public function __construct(private AuditRecordingPort $audit)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents
   *
   * Preserves the existing owner event names and audit action mapping.
   *
   * @access public
   *
   * @return array<string, string> owner event listeners
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'automation.automation_rule_executed_event' => 'onAutomationRuleExecuted',
      'automation.automation_rule_failed_event' => 'onAutomationRuleFailed',
    ];
  }

  /**
   * Method onAutomationRuleExecuted.
   *
   * Records a successful automation rule execution (e.g. a corrective
   * intervention draft auto-created from a critical non-conformity). The
   * acting principal is always the system.
   *
   * @since 1.0.0
   *
   * @param AutomationRuleExecutedEvent $event the domain event
   */
  public function onAutomationRuleExecuted(AutomationRuleExecutedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'automation.rule_executed',
      organizationId: $event->organizationId,
      subjectType: 'automation_rule',
      subjectId: $event->subjectId,
      metadata: [
        'rule_key' => $event->ruleKey,
        'intervention_id' => $event->interventionId,
      ],
      occurredAt: $event->occurredAt,
    ));
  }

  /**
   * Method onAutomationRuleFailed.
   *
   * Records a failed automation rule execution. The acting principal is
   * always the system.
   *
   * @since 1.0.0
   *
   * @param AutomationRuleFailedEvent $event the domain event
   */
  public function onAutomationRuleFailed(AutomationRuleFailedEvent $event): void
  {
    $this->audit->requestRecord(new AuditRecordRequest(
      action: 'automation.rule_failed',
      organizationId: $event->organizationId,
      subjectType: 'automation_rule',
      subjectId: $event->subjectId,
      metadata: [
        'rule_key' => $event->ruleKey,
        'error' => $event->error,
      ],
      occurredAt: $event->occurredAt,
    ));
  }
  // #endregion
}
