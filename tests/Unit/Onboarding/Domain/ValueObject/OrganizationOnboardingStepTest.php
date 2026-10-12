<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Domain\ValueObject;

use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

/**
 * Test OrganizationOnboardingStepTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationOnboardingStep::class)]
final class OrganizationOnboardingStepTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExistingContributionsPreserveEveryStepInOrder(): void
  {
    self::assertSame([
      'create_organization',
      'select_plan',
      'invite_members',
      'create_first_facility',
      'create_first_equipment',
    ], \Tests\Helper\Onboarding\RegisteredOrganizationOnboardingSteps::keys());
  }

  #[Test]
  public function testExistingContributionRequiredFlagsArePreserved(): void
  {
    self::assertSame([true, false, false, true, true], array_map(
      static fn ($definition): bool => $definition->required,
      \Tests\Helper\Onboarding\RegisteredOrganizationOnboardingSteps::definitions(),
    ));
  }

  #[Test]
  public function testIsValidAcceptsEveryKnownStep(): void
  {
    foreach (\Tests\Helper\Onboarding\RegisteredOrganizationOnboardingSteps::keys() as $step) {
      self::assertTrue(OrganizationOnboardingStep::isValid($step));
    }
  }

  #[Test]
  public function testKeySyntaxAllowsHistoricalAndNewOwnerKeys(): void
  {
    self::assertTrue(OrganizationOnboardingStep::isValid('configure_fake_feature'));
    self::assertFalse(OrganizationOnboardingStep::isValid('invalid step'));
    self::assertFalse(OrganizationOnboardingStep::isValid(''));
    self::assertTrue(OrganizationOnboardingStep::isValid(str_repeat('a', 64)));
    self::assertFalse(OrganizationOnboardingStep::isValid(str_repeat('a', 65)));
  }

  #[Test]
  public function testStepConstants(): void
  {
    self::assertSame('create_organization', OrganizationOnboardingStep::CREATE_ORGANIZATION);
    self::assertSame('select_plan', OrganizationOnboardingStep::SELECT_PLAN);
    self::assertSame('invite_members', OrganizationOnboardingStep::INVITE_MEMBERS);
    self::assertSame('create_first_facility', OrganizationOnboardingStep::CREATE_FIRST_FACILITY);
    self::assertSame('create_first_equipment', OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT);
  }
  // #endregion
}
