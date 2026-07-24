<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Adapter\Messenger;

use Automation\Application\Port\Outbound\AutomationRuleQueuePort;
use Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule\ExecuteAutomationRuleCommand;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter MessengerAutomationRuleQueueAdapter.
 *
 * Hands `ExecuteAutomationRuleCommand` straight to the message bus, which
 * routes it to the async transport. Mirrors
 * `Intervention\Infrastructure\Adapter\Publication\MessengerPublicationQueueAdapter`.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerAutomationRuleQueueAdapter implements AutomationRuleQueuePort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the MessengerAutomationRuleQueueAdapter class.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $messageBus the message bus
   */
  public function __construct(private MessageBusInterface $messageBus)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method enqueue.
   *
   * Enqueues an automation rule execution on the async transport.
   *
   * @since 1.0.0
   *
   * @param string $ruleKey the automation rule key
   * @param string $organizationId the owning organization identifier
   * @param string $subjectId the triggering subject identifier
   * @param array<string, mixed> $triggerPayload the trigger payload
   */
  public function enqueue(string $ruleKey, string $organizationId, string $subjectId, array $triggerPayload = []): void
  {
    $this->messageBus->dispatch(new ExecuteAutomationRuleCommand(
      ruleKey: $ruleKey,
      organizationId: $organizationId,
      subjectId: $subjectId,
      triggerPayload: $triggerPayload,
    ));
  }
  // #endregion
}
