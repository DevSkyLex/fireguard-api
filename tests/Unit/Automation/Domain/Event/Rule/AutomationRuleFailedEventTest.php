<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Domain\Event\Rule;

use Automation\Domain\Event\Rule\AutomationRuleFailedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AutomationRuleFailedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AutomationRuleFailedEvent::class)]
final class AutomationRuleFailedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayload(): void
  {
    $event = new AutomationRuleFailedEvent(
      'auto_create_intervention_on_critical_nc',
      'org-9',
      'nc-13',
      'draft could not be created',
    );

    self::assertSame('auto_create_intervention_on_critical_nc', $event->ruleKey);
    self::assertSame('org-9', $event->organizationId);
    self::assertSame('nc-13', $event->subjectId);
    self::assertSame('draft could not be created', $event->error);
  }

  #[Test]
  public function itStampsOccurredAtOnConstruction(): void
  {
    $before = new DateTimeImmutable();
    $event = new AutomationRuleFailedEvent('rule', 'org', 'subject', 'error');
    $after = new DateTimeImmutable();

    self::assertGreaterThanOrEqual($before, $event->occurredAt);
    self::assertLessThanOrEqual($after, $event->occurredAt);
  }
}
