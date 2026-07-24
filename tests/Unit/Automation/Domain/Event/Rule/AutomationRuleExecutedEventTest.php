<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Domain\Event\Rule;

use Automation\Domain\Event\Rule\AutomationRuleExecutedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AutomationRuleExecutedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AutomationRuleExecutedEvent::class)]
final class AutomationRuleExecutedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayload(): void
  {
    $event = new AutomationRuleExecutedEvent(
      'auto_create_intervention_on_critical_nc',
      'org-1',
      'nc-42',
      'intervention-7',
    );

    self::assertSame('auto_create_intervention_on_critical_nc', $event->ruleKey);
    self::assertSame('org-1', $event->organizationId);
    self::assertSame('nc-42', $event->subjectId);
    self::assertSame('intervention-7', $event->interventionId);
  }

  #[Test]
  public function itStampsOccurredAtOnConstruction(): void
  {
    $before = new DateTimeImmutable();
    $event = new AutomationRuleExecutedEvent('rule', 'org', 'subject', 'intervention');
    $after = new DateTimeImmutable();

    self::assertGreaterThanOrEqual($before, $event->occurredAt);
    self::assertLessThanOrEqual($after, $event->occurredAt);
  }
}
