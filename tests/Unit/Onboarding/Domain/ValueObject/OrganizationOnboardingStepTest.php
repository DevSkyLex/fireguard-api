<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Domain\ValueObject;

use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

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
  // #region Providers
  /**
   * @return iterable<string, array{string, bool}>
   */
  public static function requiredStepProvider(): iterable
  {
    yield 'create organization' => [OrganizationOnboardingStep::CREATE_ORGANIZATION, true];
    yield 'create first facility' => [OrganizationOnboardingStep::CREATE_FIRST_FACILITY, true];
    yield 'create first equipment' => [OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT, true];
    yield 'select plan' => [OrganizationOnboardingStep::SELECT_PLAN, false];
    yield 'invite members' => [OrganizationOnboardingStep::INVITE_MEMBERS, false];
    yield 'unknown step' => ['not_a_step', false];
  }
  // #endregion

  // #region Methods
  #[Test]
  public function testAllReturnsEveryStepInOrder(): void
  {
    self::assertSame([
      'create_organization',
      'select_plan',
      'invite_members',
      'create_first_facility',
      'create_first_equipment',
    ], OrganizationOnboardingStep::all());
  }

  #[Test]
  #[DataProvider('requiredStepProvider')]
  public function testIsRequired(string $step, bool $expected): void
  {
    self::assertSame($expected, OrganizationOnboardingStep::isRequired($step));
  }

  #[Test]
  public function testIsValidAcceptsEveryKnownStep(): void
  {
    foreach (OrganizationOnboardingStep::all() as $step) {
      self::assertTrue(OrganizationOnboardingStep::isValid($step));
    }
  }

  #[Test]
  public function testIsValidRejectsAnUnknownStep(): void
  {
    self::assertFalse(OrganizationOnboardingStep::isValid('not_a_step'));
    self::assertFalse(OrganizationOnboardingStep::isValid(''));
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
