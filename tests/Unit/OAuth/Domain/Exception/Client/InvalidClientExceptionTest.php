<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Exception\Client;

use OAuth\Domain\Exception\Client\InvalidClientException;
use OAuth\Domain\ValueObject\Client\ClientId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InvalidClientExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InvalidClientException::class)]
final class InvalidClientExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForIdCreatesMessage(): void
  {
    $id = new ClientId('550e8400-e29b-41d4-a716-446655440000');
    $exception = InvalidClientException::forId($id);

    $this->assertStringContainsString($id->value, $exception->getMessage());
  }

  #[Test]
  public function testInactiveCreatesMessage(): void
  {
    $id = new ClientId('550e8400-e29b-41d4-a716-446655440001');
    $exception = InvalidClientException::inactive($id);

    $this->assertStringContainsString('inactive', $exception->getMessage());
  }
  // #endregion
}
