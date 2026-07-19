<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Infrastructure\EventSubscriber;

use Automation\Application\Contract\Trigger\AutomationTriggers;
use Automation\Application\Port\Outbound\AutomationRuleQueuePort;
use Automation\Infrastructure\EventSubscriber\AutomationTriggerSubscriber;
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test AutomationTriggerSubscriberTest.
 *
 * @category Subscriber Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AutomationTriggerSubscriber::class)]
final class AutomationTriggerSubscriberTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string INSPECTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string NON_CONFORMITY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  #[Test]
  public function itSubscribesToTheExactNonConformityRecordedEventName(): void
  {
    self::assertInstanceOf(EventSubscriberInterface::class, new AutomationTriggerSubscriber(
      $this->createStub(AutomationRuleQueuePort::class),
      new NullLogger(),
    ));

    $subscribed = AutomationTriggerSubscriber::getSubscribedEvents();

    self::assertArrayHasKey('inspection.non_conformity_recorded_event', $subscribed);
    self::assertSame(AutomationTriggers::NON_CONFORMITY_RECORDED_EVENT, 'inspection.non_conformity_recorded_event');
  }

  #[Test]
  public function itDispatchesTheExecuteRuleCommandForACriticalNonConformity(): void
  {
    $ruleQueue = $this->createMock(AutomationRuleQueuePort::class);
    $ruleQueue->expects(self::once())
      ->method('enqueue')
      ->with(
        AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC,
        self::ORGANIZATION_ID,
        self::NON_CONFORMITY_ID,
        self::callback(static function (array $triggerPayload): bool {
          self::assertSame(self::NON_CONFORMITY_ID, $triggerPayload['nonConformityId']);
          self::assertSame(self::INSPECTION_ID, $triggerPayload['inspectionId']);
          self::assertSame('critical', $triggerPayload['severity']);

          return true;
        }),
      );

    $subscriber = new AutomationTriggerSubscriber($ruleQueue, new NullLogger());

    $subscriber->onNonConformityRecorded(new NonConformityRecordedEvent(
      organizationId: self::ORGANIZATION_ID,
      inspectionId: self::INSPECTION_ID,
      nonConformityId: self::NON_CONFORMITY_ID,
      severity: 'critical',
    ));
  }

  #[Test]
  public function itIgnoresNonCriticalSeverities(): void
  {
    $ruleQueue = $this->createMock(AutomationRuleQueuePort::class);
    $ruleQueue->expects(self::never())->method('enqueue');

    $subscriber = new AutomationTriggerSubscriber($ruleQueue, new NullLogger());

    foreach (['low', 'medium', 'high'] as $severity) {
      $subscriber->onNonConformityRecorded(new NonConformityRecordedEvent(
        organizationId: self::ORGANIZATION_ID,
        inspectionId: self::INSPECTION_ID,
        nonConformityId: self::NON_CONFORMITY_ID,
        severity: $severity,
      ));
    }
  }

  #[Test]
  public function itSwallowsAndLogsAQueueFailureRatherThanPropagating(): void
  {
    $ruleQueue = $this->createStub(AutomationRuleQueuePort::class);
    $ruleQueue->method('enqueue')->willThrowException(new RuntimeException('bus unavailable'));

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error');

    $subscriber = new AutomationTriggerSubscriber($ruleQueue, $logger);

    // Must not throw.
    $subscriber->onNonConformityRecorded(new NonConformityRecordedEvent(
      organizationId: self::ORGANIZATION_ID,
      inspectionId: self::INSPECTION_ID,
      nonConformityId: self::NON_CONFORMITY_ID,
      severity: 'critical',
    ));
  }
}
