<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Domain\ValueObject;

use Onboarding\Domain\ValueObject\OrganizationOnboardingState;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationOnboardingState::class)]
final class OrganizationOnboardingStateTest extends TestCase
{
  #[Test]
  public function testAllReturnsEveryKnownState(): void
  {
    self::assertSame(
      [
        OrganizationOnboardingState::IN_PROGRESS,
        OrganizationOnboardingState::COMPLETED,
        OrganizationOnboardingState::BLOCKED,
      ],
      OrganizationOnboardingState::all(),
    );
  }

  #[Test]
  public function testConstantsHoldExpectedStringValues(): void
  {
    self::assertSame('in_progress', OrganizationOnboardingState::IN_PROGRESS);
    self::assertSame('completed', OrganizationOnboardingState::COMPLETED);
    self::assertSame('blocked', OrganizationOnboardingState::BLOCKED);
  }

  #[Test]
  public function testIsValidReturnsTrueForInProgress(): void
  {
    self::assertTrue(OrganizationOnboardingState::isValid(OrganizationOnboardingState::IN_PROGRESS));
  }

  #[Test]
  public function testIsValidReturnsTrueForCompleted(): void
  {
    self::assertTrue(OrganizationOnboardingState::isValid(OrganizationOnboardingState::COMPLETED));
  }

  #[Test]
  public function testIsValidReturnsTrueForBlocked(): void
  {
    self::assertTrue(OrganizationOnboardingState::isValid(OrganizationOnboardingState::BLOCKED));
  }

  #[Test]
  public function testIsValidReturnsFalseForUnknownState(): void
  {
    self::assertFalse(OrganizationOnboardingState::isValid('archived'));
  }

  #[Test]
  public function testIsValidReturnsFalseForEmptyString(): void
  {
    self::assertFalse(OrganizationOnboardingState::isValid(''));
  }

  #[Test]
  public function testIsValidIsCaseSensitive(): void
  {
    self::assertFalse(OrganizationOnboardingState::isValid('IN_PROGRESS'));
  }
}
