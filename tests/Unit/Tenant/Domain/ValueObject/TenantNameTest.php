<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\Exception\InvalidValueException;
use Tenant\Domain\ValueObject\TenantName;

/**
 * Class TenantNameTest
 *
 * Unit tests for the TenantName Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Tenant\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TenantName::class)]
final class TenantNameTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidName
   *
   * Tests that a valid TenantName can be created.
   *
   * @access public
   *
   * @return void
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
   * Method testCannotBeCreatedWithTooShortName
   *
   * Tests that creating a TenantName with a name shorter than 2 characters throws an exception.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCannotBeCreatedWithTooShortName(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    $this->expectExceptionMessage('Tenant name must be between 2 and 100 characters.');

    new TenantName(value: 'A');
  }

  /**
   * Method testCannotBeCreatedWithTooLongName
   *
   * Tests that creating a TenantName with a name longer than 100 characters throws an exception.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCannotBeCreatedWithTooLongName(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    $this->expectExceptionMessage('Tenant name must be between 2 and 100 characters.');

    new TenantName(value: str_repeat('A', 101));
  }

  /**
   * Method testMinimumLengthNameIsValid
   *
   * Tests that a 2-character name is valid.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testMinimumLengthNameIsValid(): void
  {
    $name = new TenantName(value: 'AB');
    $this->assertEquals(expected: 'AB', actual: $name->value);
  }

  /**
   * Method testMaximumLengthNameIsValid
   *
   * Tests that a 100-character name is valid.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testMaximumLengthNameIsValid(): void
  {
    $value = str_repeat('A', 100);
    $name = new TenantName(value: $value);
    $this->assertEquals(expected: $value, actual: $name->value);
  }

  /**
   * Method testValidNamesProvider
   *
   * Tests various valid tenant names.
   *
   * @access public
   *
   * @param string $value The tenant name value.
   *
   * @return void
   */
  #[Test]
  #[DataProvider('validNamesProvider')]
  public function testValidNames(string $value): void
  {
    $name = new TenantName(value: $value);
    $this->assertEquals(expected: $value, actual: $name->value);
  }

  /**
   * Method validNamesProvider
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
  //#endregion
}
