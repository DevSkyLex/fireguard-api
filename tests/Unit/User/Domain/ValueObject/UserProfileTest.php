<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Domain\ValueObject\UserProfile;

use function str_repeat;

/**
 * Test UserProfileTest.
 *
 * @category ValueObject Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserProfile::class)]
final class UserProfileTest extends TestCase
{
  // #region Methods
  /**
   * Method testHandlesFullName.
   *
   * Tests that user profile handles
   * full name correctly.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testHandlesFullName(): void
  {
    $profile = new UserProfile('John', 'Doe');
    $this->assertEquals('John Doe', $profile->fullName());
  }

  /**
   * Method testChecksEquality.
   *
   * Tests that user profile checks
   * equality correctly.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testChecksEquality(): void
  {
    $profile1 = new UserProfile('John', 'Doe');
    $profile2 = new UserProfile('John', 'Doe');
    $profile3 = new UserProfile('Jane', 'Doe');

    $this->assertTrue($profile1->equals($profile2));
    $this->assertFalse($profile1->equals($profile3));
  }

  #[Test]
  public function testRejectsEmptyNames(): void
  {
    $this->expectException(InvalidValueException::class);

    new UserProfile('', 'Doe');
  }

  #[Test]
  public function testRejectsTooLongNames(): void
  {
    $this->expectException(InvalidValueException::class);

    new UserProfile(str_repeat('a', 101), 'Doe');
  }

  #[Test]
  public function testRejectsInvalidAvatarUrl(): void
  {
    $this->expectException(InvalidValueException::class);

    new UserProfile('John', 'Doe', 'not-a-url');
  }

  #[Test]
  public function testEqualsConsidersAvatarUrl(): void
  {
    $profile1 = new UserProfile('John', 'Doe', 'https://example.com/avatar.png');
    $profile2 = new UserProfile('John', 'Doe', 'https://example.com/avatar.png');
    $profile3 = new UserProfile('John', 'Doe', 'https://example.com/other.png');

    $this->assertTrue($profile1->equals($profile2));
    $this->assertFalse($profile1->equals($profile3));
  }
  // #endregion
}
