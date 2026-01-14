<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Exception\Session;

use Auth\Domain\Exception\Session\ValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ValidationExceptionTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ValidationException::class)]
final class ValidationExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvalidGrantTypeMessage.
   *
   * Tests invalid grant type factory message.
   */
  #[Test]
  public function testInvalidGrantTypeMessage(): void
  {
    $exception = ValidationException::invalidGrantType('password');

    $this->assertSame('Unsupported grant type: password', $exception->getMessage());
    $this->assertSame(400, $exception->getCode());
  }

  /**
   * Method testMissingFieldMessage.
   *
   * Tests missing field factory message.
   */
  #[Test]
  public function testMissingFieldMessage(): void
  {
    $exception = ValidationException::missingField('refresh_token');

    $this->assertSame('The refresh_token field is required', $exception->getMessage());
  }

  /**
   * Method testInvalidFieldMessage.
   *
   * Tests invalid field factory message.
   */
  #[Test]
  public function testInvalidFieldMessage(): void
  {
    $exception = ValidationException::invalidField('code', 'expired');

    $this->assertSame('Invalid code: expired', $exception->getMessage());
  }
  // #endregion
}
