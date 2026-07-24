<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ExecuteAutomationRuleCommand.
 *
 * Dispatched by a trigger subscriber (e.g. `AutomationTriggerSubscriber`)
 * when a domain event matches an automation rule's trigger condition.
 * Routed to the `async` transport (see `config/packages/messenger.yaml`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExecuteAutomationRuleCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $ruleKey the automation rule key (e.g. `auto_create_intervention_on_critical_nc`)
   * @param string $organizationId the organization identifier the trigger occurred in
   * @param string $subjectId the subject identifier the rule runs against (e.g. the non-conformity id)
   * @param array<string, mixed> $triggerPayload the ids/fields carried by the triggering domain event
   */
  public function __construct(
    public string $ruleKey,
    public string $organizationId,
    public string $subjectId,
    public array $triggerPayload = [],
  ) {
  }
}
