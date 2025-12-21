<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Domain\ValueObject\UserProfile;

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
  // #endregion
}
