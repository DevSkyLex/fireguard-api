<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Test TenantIdTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TenantId::class)]
final class TenantIdTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromStringCreatesId(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $id = TenantId::fromString($uuid);

    $this->assertSame($uuid, $id->value);
  }

  #[Test]
  public function testInvalidUuidThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    TenantId::fromString('invalid');
  }
  // #endregion
}
