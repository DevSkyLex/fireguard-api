<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule;

use Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule\ExecuteAutomationRuleCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\CommandMessage;

/**
 * Test ExecuteAutomationRuleCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExecuteAutomationRuleCommand::class)]
final class ExecuteAutomationRuleCommandTest extends TestCase
{
  #[Test]
  public function itRoundTripsItsProperties(): void
  {
    $command = new ExecuteAutomationRuleCommand(
      'auto_create_intervention_on_critical_nc',
      'org-1',
      'nc-42',
      ['inspectionId' => 'insp-3', 'severity' => 'critical'],
    );

    self::assertInstanceOf(CommandMessage::class, $command);
    self::assertSame('auto_create_intervention_on_critical_nc', $command->ruleKey);
    self::assertSame('org-1', $command->organizationId);
    self::assertSame('nc-42', $command->subjectId);
    self::assertSame(['inspectionId' => 'insp-3', 'severity' => 'critical'], $command->triggerPayload);
  }

  #[Test]
  public function itDefaultsTheTriggerPayloadToAnEmptyArray(): void
  {
    $command = new ExecuteAutomationRuleCommand('rule', 'org', 'subject');

    self::assertSame([], $command->triggerPayload);
  }
}
