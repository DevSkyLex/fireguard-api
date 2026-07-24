<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Application\Contract\Trigger;

use Automation\Application\Contract\Trigger\AutomationTriggers;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AutomationTriggers.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AutomationTriggers::class)]
final class AutomationTriggersTest extends TestCase
{
  #[Test]
  public function itExposesTheNonConformityRecordedEventName(): void
  {
    self::assertSame(
      'inspection.non_conformity_recorded_event',
      AutomationTriggers::NON_CONFORMITY_RECORDED_EVENT,
    );
  }

  #[Test]
  public function itExposesTheAutoCreateInterventionRuleKey(): void
  {
    self::assertSame(
      'auto_create_intervention_on_critical_nc',
      AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC,
    );
  }
}
