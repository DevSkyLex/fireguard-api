<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Domain\ValueObject\SessionId;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test SessionIdTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SessionId::class)]
final class SessionIdTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateWithValidUuid(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $id = new SessionId($uuid);

    $this->assertSame($uuid, $id->value);
  }

  #[Test]
  public function testCreateWithInvalidUuidThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new SessionId('invalid');
  }
  // #endregion
}
