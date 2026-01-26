<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Exception\Token;

use OAuth\Domain\Exception\Token\UnauthorizedGrantTypeException;
use OAuth\Domain\ValueObject\Security\GrantType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UnauthorizedGrantTypeExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UnauthorizedGrantTypeException::class)]
final class UnauthorizedGrantTypeExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testForGrantTypeCreatesMessage.
   */
  #[Test]
  public function testForGrantTypeCreatesMessage(): void
  {
    $exception = UnauthorizedGrantTypeException::forGrantType(GrantType::CLIENT_CREDENTIALS);

    $this->assertSame(
      'Grant type "CLIENT_CREDENTIALS" is not authorized for this client.',
      $exception->getMessage(),
    );
  }
  // #endregion
}
