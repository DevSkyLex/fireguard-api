<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationId::class)]
final class OrganizationIdTest extends TestCase
{
  private const string VALID_UUID = '11111111-1111-4111-8111-111111111111';

  #[Test]
  public function testFromStringRoundTripsTheValue(): void
  {
    $id = OrganizationId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function testEqualsComparesValue(): void
  {
    $left = OrganizationId::fromString(self::VALID_UUID);
    $right = OrganizationId::fromString(self::VALID_UUID);
    $other = OrganizationId::fromString('22222222-2222-4222-8222-222222222222');

    self::assertTrue($left->equals($right));
    self::assertFalse($left->equals($other));
  }

  #[Test]
  public function testRejectsInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    OrganizationId::fromString('not-a-uuid');
  }
}
