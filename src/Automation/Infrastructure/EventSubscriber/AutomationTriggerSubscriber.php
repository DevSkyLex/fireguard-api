<?php

declare(strict_types=1);

namespace Automation\Infrastructure\EventSubscriber;

use Automation\Application\Contract\Trigger\AutomationTriggers;
use Automation\Application\Port\Outbound\AutomationRuleQueuePort;
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Outbound\{DurableEventContextPort, IdempotentConsumerPort};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Subscriber AutomationTriggerSubscriber.
 *
 * Reacts to domain events from other modules (dispatched through
 * `EventDispatcherPort`, event name = `<module>.<snake_case_class>` —
 * positioned exactly like
 * {@see \Audit\Infrastructure\EventSubscriber\AuditEventSubscriber}, which
 * subscribes to the very same `NonConformityRecordedEvent`) and turns a
 * matching trigger into an `ExecuteAutomationRuleCommand`, enqueued on the
 * `main_outbox` transport through {@see AutomationRuleQueuePort}.
 *
 * The enqueue deliberately does NOT go through `CommandBusPort`: that adapter
 * demands a `HandledStamp` and throws `NoHandlerResultException` when none is
 * present, which is always the case for an async-routed message (it is handed
 * to the transport, never handled inline). The message still reached the
 * transport under that wiring — the throw happens after dispatch, at stamp
 * extraction — but every trigger then raised an exception that the catch
 * below turned into a false `error` log, hiding real dispatch failures.
 *
 * Durable triggers record their receipt and command in one main transaction;
 * failures propagate for retry. Legacy synchronous dispatches retain their
 * best-effort behavior.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AutomationTriggerSubscriber implements EventSubscriberInterface
{
  // #region Constants
  /**
   * The only severity value that currently triggers
   * `auto_create_intervention_on_critical_nc`. The rule's policy toggle may
   * later grow to cover other severities (e.g. `high`) — that change only
   * needs to happen in `ExecuteAutomationRuleHandler`'s policy check, not
   * here: this subscriber's job is solely to recognize the trigger shape,
   * not to own the business decision.
   */
  private const string TRIGGERING_SEVERITY = 'critical';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AutomationRuleQueuePort $ruleQueue the automation rule queue
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private AutomationRuleQueuePort $ruleQueue,
    private LoggerInterface $logger,
    private DurableEventContextPort $eventContext,
    private IdempotentConsumerPort $eventConsumer,
  ) {
  }
  // #endregion

  /**
   * Method getSubscribedEvents.
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      AutomationTriggers::NON_CONFORMITY_RECORDED_EVENT => 'onNonConformityRecorded',
    ];
  }

  /**
   * Method onNonConformityRecorded.
   *
   * @since 1.0.0
   *
   * @param NonConformityRecordedEvent $event the domain event
   */
  public function onNonConformityRecorded(NonConformityRecordedEvent $event): void
  {
    if (self::TRIGGERING_SEVERITY !== $event->severity) {
      return;
    }

    try {
      $enqueue = function () use ($event): void {
        $this->ruleQueue->enqueue(
          ruleKey: AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC,
          organizationId: $event->organizationId,
          subjectId: $event->nonConformityId,
          triggerPayload: [
            'nonConformityId' => $event->nonConformityId,
            'inspectionId' => $event->inspectionId,
            'severity' => $event->severity,
          ],
        );
      };
      $eventId = $this->eventContext->eventId();
      if (null === $eventId) {
        $enqueue();
      } else {
        $this->eventConsumer->consume($eventId, 'automation.trigger', $enqueue);
      }
    } catch (Throwable $exception) {
      $this->logger->error('Failed to dispatch automation rule execution', [
        'rule_key' => AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC,
        'organization_id' => $event->organizationId,
        'non_conformity_id' => $event->nonConformityId,
        'error' => $exception->getMessage(),
      ]);
      if (null !== $this->eventContext->eventId()) {
        throw $exception;
      }
    }
  }
}
