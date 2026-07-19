<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Contract\Trigger\AutomationTriggers;
use Automation\Application\Port\Outbound\{AutomationPolicyPort, AutomationRunPort};
use Automation\Application\UseCase\Command\Rule\ExecuteAutomationRule\{ExecuteAutomationRuleCommand, ExecuteAutomationRuleHandler};
use Automation\Domain\Event\Rule\{AutomationRuleExecutedEvent, AutomationRuleFailedEvent};
use DateTimeImmutable;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft};
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * Test ExecuteAutomationRuleHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExecuteAutomationRuleHandler::class)]
final class ExecuteAutomationRuleHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

  private const string NON_CONFORMITY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b02';

  private const string INSPECTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b03';

  private const string RUN_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b04';

  #[Test]
  public function itSkipsExecutionWhenTheRunIsAlreadyClaimed(): void
  {
    $runs = $this->createMock(AutomationRunPort::class);
    $runs->method('reserveRun')->willReturn(null);
    $runs->expects(self::never())->method('markSkipped');
    $runs->expects(self::never())->method('markSucceeded');
    $runs->expects(self::never())->method('markFailed');

    $policy = $this->createMock(AutomationPolicyPort::class);
    $policy->expects(self::never())->method('policyFor');

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $result = $this->handler($runs, $policy, $draftFactory, $this->createStub(EventDispatcherPort::class))(self::command());

    self::assertInstanceOf(VoidResult::class, $result);
  }

  #[Test]
  public function itMarksTheRunSkippedWhenThePolicyToggleIsOff(): void
  {
    $runs = $this->createMock(AutomationRunPort::class);
    $runs->method('reserveRun')->willReturn(self::RUN_ID);
    $runs->expects(self::once())->method('markSkipped')->with(self::RUN_ID);
    $runs->expects(self::never())->method('markSucceeded');
    $runs->expects(self::never())->method('markFailed');

    $policy = $this->createStub(AutomationPolicyPort::class);
    $policy->method('policyFor')->willReturn(new AutomationPolicy(false, ['critical' => 1]));

    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::never())->method('create');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->handler($runs, $policy, $draftFactory, $eventDispatcher)(self::command());
  }

  #[Test]
  public function itCreatesTheCorrectiveInterventionDraftWithTheSpecifiedContentsOnSuccess(): void
  {
    $runs = $this->createMock(AutomationRunPort::class);
    $runs->method('reserveRun')->willReturn(self::RUN_ID);
    $runs->expects(self::once())->method('markSucceeded')->with(self::RUN_ID, 'intervention-1');
    $runs->expects(self::never())->method('markFailed');
    $runs->expects(self::never())->method('markSkipped');

    $policy = $this->createStub(AutomationPolicyPort::class);
    $policy->method('policyFor')->willReturn(new AutomationPolicy(true, ['critical' => 3, 'high' => 7]));

    $captured = null;
    $draftFactory = $this->createMock(InterventionDraftFactoryPort::class);
    $draftFactory->expects(self::once())
      ->method('create')
      ->willReturnCallback(function (CreateInterventionDraftRequest $request) use (&$captured): CreatedInterventionDraft {
        $captured = $request;

        return new CreatedInterventionDraft('intervention-1', 1, 1);
      });

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (AutomationRuleExecutedEvent $event): bool {
        self::assertSame(AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC, $event->ruleKey);
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame(self::NON_CONFORMITY_ID, $event->subjectId);
        self::assertSame('intervention-1', $event->interventionId);

        return true;
      }));

    $this->handler($runs, $policy, $draftFactory, $eventDispatcher)(self::command());

    self::assertInstanceOf(CreateInterventionDraftRequest::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame('inspection_campaign', $captured->type);
    self::assertSame('automation:auto_create_intervention_on_critical_nc', $captured->origin);
    self::assertSame('urgent', $captured->priority);
    self::assertNull($captured->actorUserId);
    self::assertNull($captured->siteId);
    self::assertCount(1, $captured->workItems);
    self::assertSame('inspection', $captured->workItems[0]->action);
    self::assertStringContainsString(self::NON_CONFORMITY_ID, (string) $captured->workItems[0]->target);
    // now (2026-01-05, stubbed clock) + nonConformitySlaDays['critical'] (3 days).
    self::assertEquals(new DateTimeImmutable('2026-01-08T00:00:00+00:00'), $captured->dueAt);
  }

  #[Test]
  public function itRecordsAFailureWithoutRethrowingWhenTheDraftFactoryThrows(): void
  {
    $runs = $this->createMock(AutomationRunPort::class);
    $runs->method('reserveRun')->willReturn(self::RUN_ID);
    $runs->expects(self::once())->method('markFailed')->with(self::RUN_ID, 'template not found');
    $runs->expects(self::never())->method('markSucceeded');
    $runs->expects(self::never())->method('markSkipped');

    $policy = $this->createStub(AutomationPolicyPort::class);
    $policy->method('policyFor')->willReturn(new AutomationPolicy(true, ['critical' => 1]));

    $draftFactory = $this->createStub(InterventionDraftFactoryPort::class);
    $draftFactory->method('create')->willThrowException(new RuntimeException('template not found'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (AutomationRuleFailedEvent $event): bool {
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame(self::NON_CONFORMITY_ID, $event->subjectId);
        self::assertSame('template not found', $event->error);

        return true;
      }));

    // Must not throw: the run row already guards against a Messenger retry
    // producing a duplicate draft.
    $result = $this->handler($runs, $policy, $draftFactory, $eventDispatcher)(self::command());

    self::assertInstanceOf(VoidResult::class, $result);
  }

  private static function command(): ExecuteAutomationRuleCommand
  {
    return new ExecuteAutomationRuleCommand(
      ruleKey: AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC,
      organizationId: self::ORGANIZATION_ID,
      subjectId: self::NON_CONFORMITY_ID,
      triggerPayload: [
        'nonConformityId' => self::NON_CONFORMITY_ID,
        'inspectionId' => self::INSPECTION_ID,
        'severity' => 'critical',
      ],
    );
  }

  private function handler(
    AutomationRunPort $runs,
    AutomationPolicyPort $policy,
    InterventionDraftFactoryPort $draftFactory,
    EventDispatcherPort $eventDispatcher,
  ): ExecuteAutomationRuleHandler {
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-05T00:00:00+00:00'));

    return new ExecuteAutomationRuleHandler($runs, $policy, $draftFactory, $eventDispatcher, $clock, new NullLogger());
  }
}
