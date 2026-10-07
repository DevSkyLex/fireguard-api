<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Domain\Model;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\Supplier;
use Shared\Domain\Exception\InvalidValueException;

use function array_fill;

/**
 * Class SupplierTest
 *
 * Verifies organization identity, declarative contacts and archival restrictions.
 *
 * @category Tests
 */
#[CoversClass(Supplier::class)]
final class SupplierTest extends TestCase
{
  // #region Constants
  /**
   * Constant SUPPLIER
   */
  private const string SUPPLIER = '018fa001-1111-7111-8111-111111111111';

  /**
   * Constant ORGANIZATION
   */
  private const string ORGANIZATION = '018fa002-1111-7111-8111-111111111111';
  // #endregion

  // #region Methods
  /**
   * Method testCreationNormalizesContactFieldsAndPreservesIdentity
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCreationNormalizesContactFieldsAndPreservesIdentity(): void
  {
    $supplier = Supplier::create(self::SUPPLIER, self::ORGANIZATION, ' Supplier Example ', ' SUP-1 ', ' purchases@example.com ', ' 01 23 45 67 89 ', [['name' => ' Alice ', 'email' => ' alice@example.com ', 'role' => ' Sales ']], $this->now());

    self::assertSame(self::SUPPLIER, $supplier->id);
    self::assertSame(self::ORGANIZATION, $supplier->organizationId);
    self::assertSame('Supplier Example', $supplier->name());
    self::assertSame('SUP-1', $supplier->code());
    self::assertSame('purchases@example.com', $supplier->email());
    self::assertSame('01 23 45 67 89', $supplier->phone());
    self::assertSame([['name' => 'Alice', 'email' => 'alice@example.com', 'phone' => null, 'role' => 'Sales']], $supplier->contacts());
    self::assertTrue($supplier->isActive());
    self::assertNull($supplier->archivedAt());
    self::assertSame(1, $supplier->revision());
    self::assertEquals($this->now(), $supplier->createdAt);
    self::assertEquals($this->now(), $supplier->updatedAt());
  }

  /**
   * Method testBlankOptionalFieldsBecomeNull
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testBlankOptionalFieldsBecomeNull(): void
  {
    $supplier = Supplier::create(self::SUPPLIER, self::ORGANIZATION, 'Example', ' ', ' ', ' ', [], $this->now());

    self::assertNull($supplier->code());
    self::assertNull($supplier->email());
    self::assertNull($supplier->phone());
    self::assertSame([], $supplier->contacts());
  }

  /**
   * Method testActiveChangeAdvancesRevisionAndRetainsIdentity
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testActiveChangeAdvancesRevisionAndRetainsIdentity(): void
  {
    $supplier = $this->supplier();
    $changedAt = new DateTimeImmutable('2026-10-07T10:00:00+00:00');
    $supplier->change(1, 'Changed supplier', 'NEW', 'new@example.com', '+33123456789', [['name' => 'Bob']], $changedAt);

    self::assertSame(self::SUPPLIER, $supplier->id);
    self::assertSame(self::ORGANIZATION, $supplier->organizationId);
    self::assertSame('Changed supplier', $supplier->name());
    self::assertSame('NEW', $supplier->code());
    self::assertSame([['name' => 'Bob', 'email' => null, 'phone' => null, 'role' => null]], $supplier->contacts());
    self::assertSame(2, $supplier->revision());
    self::assertEquals($changedAt, $supplier->updatedAt());
    self::assertEquals($this->now(), $supplier->createdAt);
  }

  /**
   * Method testInvalidReplacementDoesNotPartiallyChangeSupplier
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvalidReplacementDoesNotPartiallyChangeSupplier(): void
  {
    $supplier = $this->supplier();

    try {
      $supplier->change(1, 'New name', 'NEW', 'invalid-email', null, [], $this->now());
      self::fail('Expected invalid replacement contact data.');
    } catch (ProcurementException $exception) {
      self::assertSame('invalid', $exception->errorCode);
    }

    self::assertSame('Original supplier', $supplier->name());
    self::assertNull($supplier->code());
    self::assertSame('supplier@example.com', $supplier->email());
    self::assertSame(1, $supplier->revision());
  }

  /**
   * Method testArchivalRetainsContactsAndStopsEdits
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testArchivalRetainsContactsAndStopsEdits(): void
  {
    $supplier = $this->supplier();
    $archivedAt = new DateTimeImmutable('2026-10-07T10:00:00+00:00');
    $supplier->archive(1, $archivedAt);
    $supplier->archive(2, new DateTimeImmutable('2026-10-08T10:00:00+00:00'));

    self::assertFalse($supplier->isActive());
    self::assertEquals($archivedAt, $supplier->archivedAt());
    self::assertEquals($archivedAt, $supplier->updatedAt());
    self::assertSame('supplier@example.com', $supplier->email());
    self::assertSame(2, $supplier->revision());
    $this->expectException(ProcurementException::class);
    $this->expectExceptionMessage('archived');

    $supplier->change(2, 'Changed', null, null, null, [], $archivedAt);
  }

  /**
   * Method testStaleRevisionRejectsMutationWithoutChanges
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testStaleRevisionRejectsMutationWithoutChanges(): void
  {
    $supplier = $this->supplier();

    try {
      $supplier->archive(0, $this->now());
      self::fail('Expected stale revision.');
    } catch (ProcurementException $exception) {
      self::assertSame('stale', $exception->errorCode);
    }

    self::assertTrue($supplier->isActive());
    self::assertSame(1, $supplier->revision());
  }

  /**
   * Method testContactStructureAndValuesAreValidated
   *
   * @access public
   *
   * @param array<mixed> $contacts the invalid contact data
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidContacts')]
  public function testContactStructureAndValuesAreValidated(array $contacts): void
  {
    $this->expectException(ProcurementException::class);

    Supplier::create(self::SUPPLIER, self::ORGANIZATION, 'Example', null, null, null, $contacts, $this->now());
  }

  /**
   * Method invalidContacts
   *
   * @access public
   *
   * @return iterable<string, array{array<mixed>}> malformed contacts
   */
  public static function invalidContacts(): iterable
  {
    yield 'not a list' => [['contact' => ['name' => 'Alice']]];
    yield 'missing name' => [[['email' => 'alice@example.com']]];
    yield 'blank name' => [[['name' => ' ']]];
    yield 'invalid email' => [[['name' => 'Alice', 'email' => 'invalid']]];
    yield 'unknown executable field' => [[['name' => 'Alice', 'callback' => 'run']]];
    yield 'non-string phone' => [[['name' => 'Alice', 'phone' => 123]]];
    yield 'too many contacts' => [array_fill(0, 51, ['name' => 'Alice'])];
  }

  /**
   * Method testInvalidUuidIsRejected
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testInvalidUuidIsRejected(): void
  {
    $this->expectException(InvalidValueException::class);

    Supplier::create('invalid', self::ORGANIZATION, 'Example', null, null, null, [], $this->now());
  }

  /**
   * Method testRestorationKeepsArchiveAndRevision
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationKeepsArchiveAndRevision(): void
  {
    $archivedAt = new DateTimeImmutable('2026-10-07T10:00:00+00:00');
    $supplier = Supplier::reconstitute(self::SUPPLIER, self::ORGANIZATION, 'Supplier', 'SUP', 'supplier@example.com', null, [], $archivedAt, $this->now(), $archivedAt, 4);

    self::assertFalse($supplier->isActive());
    self::assertSame(4, $supplier->revision());
    self::assertSame('SUP', $supplier->code());
    self::assertEquals($this->now(), $supplier->createdAt);
    self::assertEquals($archivedAt, $supplier->archivedAt());
  }

  /**
   * Method testRestorationRejectsNonPositiveRevision
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationRejectsNonPositiveRevision(): void
  {
    $this->expectException(ProcurementException::class);

    Supplier::reconstitute(self::SUPPLIER, self::ORGANIZATION, 'Supplier', null, null, null, [], null, $this->now(), $this->now(), 0);
  }

  /**
   * Method testUpdateCannotMoveHistoryBackwards
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUpdateCannotMoveHistoryBackwards(): void
  {
    $supplier = $this->supplier();
    $this->expectException(ProcurementException::class);

    $supplier->change(1, 'Changed', null, null, null, [], new DateTimeImmutable('2026-10-05T10:00:00+00:00'));
  }

  /**
   * Method supplier
   *
   * @access private
   *
   * @return Supplier an active supplier
   */
  private function supplier(): Supplier
  {
    return Supplier::create(self::SUPPLIER, self::ORGANIZATION, 'Original supplier', null, 'supplier@example.com', null, [], $this->now());
  }

  /**
   * Method now
   *
   * @access private
   *
   * @return DateTimeImmutable the explicit test instant
   */
  private function now(): DateTimeImmutable
  {
    return new DateTimeImmutable('2026-10-06T10:00:00+00:00');
  }
  // #endregion
}
