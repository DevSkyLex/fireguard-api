<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Domain\ValueObject\UserStatus;

/**
 * Test UserStatusTest
 * @final
 *
 * Unit tests for the UserStatus Enum.
 *
 * @category ValueObject Tests
 * @package Tests\Unit\User\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserStatus::class)]
final class UserStatusTest extends TestCase
{
  //#region Methods
  /**
   * Method testHasCorrectMethods
   *
   * Tests that user status has
   * correct methods.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testHasCorrectMethods(): void
  {
    $active = UserStatus::ACTIVE;
    $inactive = UserStatus::INACTIVE;

    $this->assertTrue($active->isActive());
    $this->assertTrue($active->canLogin());
    $this->assertEquals('Active', $active->label());

    $this->assertFalse($inactive->isActive());
    $this->assertFalse($inactive->canLogin());
    $this->assertEquals('Inactive', $inactive->label());
  }
  //#endregion
}
