<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Token\ValidateToken;

use OAuth\Application\UseCase\Query\Token\ValidateToken\ValidateTokenResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ValidateTokenResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ValidateTokenResult::class)]
final class ValidateTokenResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvalidFactory(): void
  {
    $result = ValidateTokenResult::invalid('Invalid token');

    self::assertFalse($result->valid);
    self::assertSame('Invalid token', $result->errorMessage);
  }
  // #endregion
}
