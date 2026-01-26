<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Domain\ValueObject\PermissionId;

/**
 * Test PermissionIdTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PermissionId::class)]
final class PermissionIdTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateWithValidUuid(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440050';
    $id = new PermissionId($uuid);

    $this->assertSame($uuid, $id->value);
  }

  #[Test]
  public function testCreateWithInvalidUuidThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new PermissionId('invalid');
  }
  // #endregion
}
