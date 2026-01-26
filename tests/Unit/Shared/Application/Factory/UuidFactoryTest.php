<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Factory;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test UuidFactoryTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UuidFactory::class)]
final class UuidFactoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateBuildsUuidValueObject(): void
  {
    $factory = $this->createFactory('550e8400-e29b-41d4-a716-446655440000');

    $uuid = $factory->create(Uuid::class);

    self::assertInstanceOf(Uuid::class, $uuid);
    self::assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->value);
  }

  #[Test]
  public function testGenerateRawReturnsGeneratorValue(): void
  {
    $factory = $this->createFactory('123e4567-e89b-12d3-a456-426614174000');

    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $factory->generateRaw());
  }
  // #endregion

  private function createFactory(string $value): UuidFactory
  {
    return new UuidFactory(generator: new FakeUuidGenerator($value));
  }
}

final class FakeUuidGenerator implements UuidGeneratorPort
{
  public function __construct(private readonly string $value)
  {
  }

  public function generate(): string
  {
    return $this->value;
  }
}
