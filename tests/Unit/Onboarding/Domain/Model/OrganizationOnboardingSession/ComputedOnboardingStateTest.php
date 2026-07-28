<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Domain\Model\OrganizationOnboardingSession;

use Onboarding\Domain\Model\OrganizationOnboardingSession\ComputedOnboardingState;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ComputedOnboardingStateTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComputedOnboardingState::class)]
final class ComputedOnboardingStateTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItExposesAnInProgressState(): void
  {
    $state = new ComputedOnboardingState(
      state: 'in_progress',
      nextStep: 'create_first_facility',
      blockedReason: null,
      targetOrganizationId: 'organization-1',
      targetOrganizationName: 'Acme Fire Safety',
    );

    self::assertSame('in_progress', $state->state);
    self::assertSame('create_first_facility', $state->nextStep);
    self::assertNull($state->blockedReason);
    self::assertSame('organization-1', $state->targetOrganizationId);
    self::assertSame('Acme Fire Safety', $state->targetOrganizationName);
  }

  #[Test]
  public function testItExposesABlockedState(): void
  {
    $state = new ComputedOnboardingState(
      state: 'blocked',
      nextStep: null,
      blockedReason: 'plan_required',
      targetOrganizationId: null,
      targetOrganizationName: null,
    );

    self::assertSame('blocked', $state->state);
    self::assertNull($state->nextStep);
    self::assertSame('plan_required', $state->blockedReason);
    self::assertNull($state->targetOrganizationId);
    self::assertNull($state->targetOrganizationName);
  }
  // #endregion
}
