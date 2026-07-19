<?php

declare(strict_types=1);

namespace Automation\Application\Port\Outbound;

/**
 * Port AutomationRuleQueuePort.
 *
 * Enqueues an automation rule execution for asynchronous processing.
 *
 * This exists instead of dispatching `ExecuteAutomationRuleCommand` through
 * `Shared\Application\Port\Inbound\CommandBusPort`: that adapter requires a
 * `HandledStamp` on the returned envelope and throws
 * `NoHandlerResultException` when there is none. An async-routed message is
 * handed to the transport rather than handled inline, so it carries no
 * HandledStamp — dispatching it through the command bus always fails. The
 * same reasoning backs `Intervention\...\PublicationQueuePort` and
 * `Import\...\ImportJobQueuePort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AutomationRuleQueuePort
{
  /**
   * Enqueues an automation rule execution.
   *
   * @param string $ruleKey the automation rule key
   * @param string $organizationId the owning organization identifier
   * @param string $subjectId the triggering subject identifier (idempotence key)
   * @param array<string, mixed> $triggerPayload the trigger payload
   */
  public function enqueue(string $ruleKey, string $organizationId, string $subjectId, array $triggerPayload = []): void;
}
