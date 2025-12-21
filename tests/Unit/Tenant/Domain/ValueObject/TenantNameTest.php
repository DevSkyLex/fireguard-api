<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Tenant\Domain\ValueObject\TenantName;

use function str_repeat;

/**
 * Class TenantNameTest.
 *
 * Unit tests for the TenantName Value Object.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TenantName::class)]
final class TenantNameTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreatedWithValidName.
   *
   * Tests that a valid TenantName can be created.
   */
  #[Test]
  public function testCanBeCreatedWithValidName(): void
  {
    $value = 'Acme Corporation';
    $name = new TenantName(value: $value);

    $this->assertEquals(expected: $value, actual: $name->value);
    $this->assertEquals(expected: $value, actual: (string) $name);
  }

  /**
   * Method testCannotBeCreatedWithTooShortName.
   *
   * Tests that creating a TenantName with a name shorter than 2 characters throws an exception.
   */
  #[Test]
  public function testCannotBeCreatedWithTooShortName(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    $this->expectExceptionMessage('Tenant name must be between 2 and 100 characters.');

    new TenantName(value: 'A');
  }

  /**
   * Method testCannotBeCreatedWithTooLongName.
   *
   * Tests that creating a TenantName with a name longer than 100 characters throws an exception.
   */
  #[Test]
  public function testCannotBeCreatedWithTooLongName(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    $this->expectExceptionMessage('Tenant name must be between 2 and 100 characters.');

    new TenantName(value: str_repeat('A', 101));
  }

  /**
   * Method testMinimumLengthNameIsValid.
   *
   * Tests that a 2-character name is valid.
   */
  #[Test]
  public function testMinimumLengthNameIsValid(): void
  {
    $name = new TenantName(value: 'AB');
    $this->assertEquals(expected: 'AB', actual: $name->value);
  }

  /**
   * Method testMaximumLengthNameIsValid.
   *
   * Tests that a 100-character name is valid.
   */
  #[Test]
  public function testMaximumLengthNameIsValid(): void
  {
    $value = str_repeat('A', 100);
    $name = new TenantName(value: $value);
    $this->assertEquals(expected: $value, actual: $name->value);
  }

  /**
   * Method testValidNamesProvider.
   *
   * Tests various valid tenant names.
   *
   * @param string $value the tenant name value
   */
  #[Test]
  #[DataProvider('validNamesProvider')]
  public function testValidNames(string $value): void
  {
    $name = new TenantName(value: $value);
    $this->assertEquals(expected: $value, actual: $name->value);
  }

  /**
   * Method validNamesProvider.
   *
   * @return array<string, array{string}>
   */
  public static function validNamesProvider(): array
  {
    return [
      'simple name' => ['Acme'],
      'name with spaces' => ['Acme Corporation'],
      'name with numbers' => ['Company 123'],
      'name with special chars' => ['Société Générale'],
      'unicode name' => ['日本企業'],
    ];
  }
  // #endregion
}
