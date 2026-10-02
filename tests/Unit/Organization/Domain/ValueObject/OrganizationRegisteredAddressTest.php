<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationRegisteredAddress;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function str_repeat;

#[CoversClass(OrganizationRegisteredAddress::class)]
final class OrganizationRegisteredAddressTest extends TestCase
{
  #[Test]
  public function testPartialAddressIsNormalizedWithoutNationalRequirements(): void
  {
    $address = OrganizationRegisteredAddress::fromArray([
      'line1' => '  10 rue du Test  ',
      'line2' => ' ',
      'postalCode' => ' SW1A 1AA ',
      'countryCode' => ' gb ',
    ]);

    self::assertSame([
      'line1' => '10 rue du Test',
      'line2' => null,
      'postalCode' => 'SW1A 1AA',
      'city' => null,
      'region' => null,
      'countryCode' => 'GB',
    ], $address->toArray());
    self::assertFalse($address->isEmpty());
  }

  #[Test]
  public function testEmptyAndWhitespaceOnlyAddressesAreEmpty(): void
  {
    self::assertTrue(OrganizationRegisteredAddress::fromArray([])->isEmpty());
    self::assertTrue(OrganizationRegisteredAddress::fromArray(['line1' => ' ', 'countryCode' => ' '])->isEmpty());
  }

  /**
   * @param array<string, ?string> $components
   */
  #[Test]
  #[DataProvider('invalidAddresses')]
  public function testRejectsInvalidComponents(array $components): void
  {
    $this->expectException(InvalidValueException::class);
    OrganizationRegisteredAddress::fromArray($components);
  }

  /**
   * @return iterable<string, array{array<string, ?string>}>
   */
  public static function invalidAddresses(): iterable
  {
    yield 'unassigned ISO code' => [['countryCode' => 'ZZ']];
    yield 'country name' => [['countryCode' => 'France']];
    yield 'line length' => [['line1' => str_repeat('a', 256)]];
    yield 'postal length' => [['postalCode' => str_repeat('a', 33)]];
    yield 'city length' => [['city' => str_repeat('a', 129)]];
    yield 'region length' => [['region' => str_repeat('a', 129)]];
  }
}
