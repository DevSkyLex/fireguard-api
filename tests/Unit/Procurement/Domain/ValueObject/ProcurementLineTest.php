<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\ValueObject\ProcurementLine;
use Shared\Domain\Exception\InvalidValueException;
use stdClass;

use function str_repeat;

use const INF;

/**
 * Class ProcurementLineTest
 *
 * Verifies distinct goods identities and exact retained physical quantities.
 *
 * @category Tests
 */
#[CoversClass(ProcurementLine::class)]
final class ProcurementLineTest extends TestCase
{
  // #region Constants
  /**
   * Constant LINE
   */
  private const string LINE = '018fa001-1111-7111-8111-111111111111';

  /**
   * Constant PART
   */
  private const string PART = '018fa002-1111-7111-8111-111111111111';

  #[Test]
  public function articleCodeLabelAndUnitStayFrozenThroughReceiptsAndReturns(): void
  {
    $line = ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '2.500000', null, 'REF', 'Foam concentrate', 'litre');
    $received = $line->receive('1.250000');
    $returned = $received->returnReceived('0.250000');
    self::assertSame('REF', $returned->partCode);
    self::assertSame('Foam concentrate', $returned->partLabel);
    self::assertSame('litre', $returned->partUnit);
    self::assertSame('1.250000', $returned->receivedQuantity);
    self::assertSame('0.250000', $returned->returnedQuantity);
  }
  // #endregion

  // #region Methods
  /**
   * Method testConsumableFractionsAndCostsAreCanonicalExactStrings
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testConsumableFractionsAndCostsAreCanonicalExactStrings(): void
  {
    $line = ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '0001.25', '00012.123456');

    self::assertSame(self::LINE, $line->id);
    self::assertSame('part', $line->kind);
    self::assertSame(self::PART, $line->partId);
    self::assertNull($line->typeCode);
    self::assertSame([], $line->identityTemplate);
    self::assertSame('1.250000', $line->quantity);
    self::assertSame('12.123456', $line->unitCost);
    self::assertSame('0.000000', $line->receivedQuantity);
    self::assertSame('0.000000', $line->returnedQuantity);
    self::assertSame('1.250000', $line->remainingQuantity());
  }

  /**
   * Method testUnknownUnitCostStaysDifferentFromKnownZero
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testUnknownUnitCostStaysDifferentFromKnownZero(): void
  {
    $unknown = ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '1', null);
    $knownZero = ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '1', '0');

    self::assertNull($unknown->unitCost);
    self::assertSame('0.000000', $knownZero->unitCost);
  }

  /**
   * Method testEquipmentKeepsDeclarativeTemplateAndWholeUnits
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testEquipmentKeepsDeclarativeTemplateAndWholeUnits(): void
  {
    $template = ['name' => 'Portable extinguisher', 'brand' => 'Example', 'technicalProperties' => [['key' => 'capacity', 'value' => '6', 'unit' => 'kg']]];
    $line = ProcurementLine::create(self::LINE, 'equipment_to_individualize', null, ' fire_extinguisher ', $template, '3.000000', '120.5');

    self::assertSame('equipment_to_individualize', $line->kind);
    self::assertNull($line->partId);
    self::assertSame('fire_extinguisher', $line->typeCode);
    self::assertSame($template, $line->identityTemplate);
    self::assertSame('3.000000', $line->quantity);
    self::assertSame('120.500000', $line->unitCost);
    self::assertSame('1.000000', $line->receive('1')->receivedQuantity);
  }

  /**
   * Method testReceivedAndReturnedSnapshotsDoNotMutateOriginalLine
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testReceivedAndReturnedSnapshotsDoNotMutateOriginalLine(): void
  {
    $original = ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '1.25', '10');
    $received = $original->receive('0.75');
    $returned = $received->returnReceived('0.25');

    self::assertSame('0.000000', $original->receivedQuantity);
    self::assertSame('0.750000', $received->receivedQuantity);
    self::assertSame('0.000000', $received->returnedQuantity);
    self::assertSame('0.750000', $returned->receivedQuantity);
    self::assertSame('0.250000', $returned->returnedQuantity);
    self::assertSame('0.500000', $returned->returnableQuantity());
    self::assertSame('0.500000', $returned->remainingQuantity());
    self::assertSame($original->id, $returned->id);
    self::assertSame($original->unitCost, $returned->unitCost);
  }

  /**
   * Method testInvalidQuantitiesAreRejected
   *
   * @access public
   *
   * @param string $quantity the invalid ordered quantity
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidQuantities')]
  public function testInvalidQuantitiesAreRejected(string $quantity): void
  {
    $this->expectException(ProcurementException::class);

    ProcurementLine::create(self::LINE, 'part', self::PART, null, [], $quantity, null);
  }

  /**
   * Method invalidQuantities
   *
   * @access public
   *
   * @return iterable<string, array{string}> quantity bounds
   */
  public static function invalidQuantities(): iterable
  {
    yield 'zero' => ['0'];
    yield 'negative' => ['-0.000001'];
    yield 'above maximum' => ['100000.000001'];
  }

  /**
   * Method testMaximumQuantityAndSmallestFractionAreAccepted
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testMaximumQuantityAndSmallestFractionAreAccepted(): void
  {
    self::assertSame('100000.000000', ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '100000', null)->quantity);
    self::assertSame('0.000001', ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '0.000001', null)->quantity);
  }

  /**
   * Method testIdentitiesCannotMixStockAndIndividualEquipment
   *
   * @access public
   *
   * @param string $kind the goods category
   * @param ?string $partId the article identity
   * @param ?string $typeCode the equipment type
   * @param array<string, mixed> $template the equipment template
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidIdentities')]
  public function testIdentitiesCannotMixStockAndIndividualEquipment(string $kind, ?string $partId, ?string $typeCode, array $template): void
  {
    $this->expectException(ProcurementException::class);

    ProcurementLine::create(self::LINE, $kind, $partId, $typeCode, $template, '2', null);
  }

  /**
   * Method invalidIdentities
   *
   * @access public
   *
   * @return iterable<string, array{string, ?string, ?string, array<string, mixed>}> invalid goods identities
   */
  public static function invalidIdentities(): iterable
  {
    yield 'unknown category' => ['service', self::PART, null, []];
    yield 'missing part' => ['part', null, null, []];
    yield 'part with equipment type' => ['part', self::PART, 'fire_extinguisher', []];
    yield 'part with identity template' => ['part', self::PART, null, ['name' => 'Wrong inventory']];
    yield 'equipment with part' => ['equipment_to_individualize', self::PART, 'fire_extinguisher', []];
    yield 'equipment without type' => ['equipment_to_individualize', null, null, []];
    yield 'equipment with blank type' => ['equipment_to_individualize', null, ' ', []];
  }

  /**
   * Method testIndividualEquipmentRejectsFractionalCreationReceiptAndReturn
   *
   * @access public
   *
   * @param string $ordered the ordered quantity
   * @param string $received the gross received quantity
   * @param string $returned the returned quantity
   *
   * @return void
   */
  #[Test]
  #[DataProvider('fractionalEquipment')]
  public function testIndividualEquipmentRejectsFractionalCreationReceiptAndReturn(string $ordered, string $received, string $returned): void
  {
    $this->expectException(ProcurementException::class);

    ProcurementLine::reconstitute(self::LINE, 'equipment_to_individualize', null, 'fire_extinguisher', [], $ordered, null, $received, $returned);
  }

  /**
   * Method fractionalEquipment
   *
   * @access public
   *
   * @return iterable<string, array{string, string, string}> fractional equipment histories
   */
  public static function fractionalEquipment(): iterable
  {
    yield 'fractional order' => ['1.5', '0', '0'];
    yield 'fractional receipt' => ['2', '0.5', '0'];
    yield 'fractional return' => ['2', '1', '0.5'];
  }

  /**
   * Method testRestorationRejectsImpossiblePhysicalHistory
   *
   * @access public
   *
   * @param string $received the restored gross received quantity
   * @param string $returned the restored returned quantity
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidHistory')]
  public function testRestorationRejectsImpossiblePhysicalHistory(string $received, string $returned): void
  {
    $this->expectException(ProcurementException::class);

    ProcurementLine::reconstitute(self::LINE, 'part', self::PART, null, [], '5', null, $received, $returned);
  }

  /**
   * Method invalidHistory
   *
   * @access public
   *
   * @return iterable<string, array{string, string}> impossible historical quantities
   */
  public static function invalidHistory(): iterable
  {
    yield 'receipt over order' => ['5.000001', '0'];
    yield 'negative receipt' => ['-1', '0'];
    yield 'return over receipt' => ['2', '2.000001'];
    yield 'negative return' => ['2', '-1'];
  }

  /**
   * Method testOverReceptionDoesNotPartiallyChangeLine
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOverReceptionDoesNotPartiallyChangeLine(): void
  {
    $line = ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '1.25', null)->receive('1');

    try {
      $line->receive('0.250001');
      self::fail('Expected an over-reception failure.');
    } catch (ProcurementException $exception) {
      self::assertSame('invalid', $exception->errorCode);
    }

    self::assertSame('1.000000', $line->receivedQuantity);
    self::assertSame('0.250000', $line->remainingQuantity());
  }

  /**
   * Method testAlreadyReturnedUnitsCannotBeReturnedAgain
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testAlreadyReturnedUnitsCannotBeReturnedAgain(): void
  {
    $line = ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '2', null)->receive('2')->returnReceived('1.5');
    $this->expectException(ProcurementException::class);

    $line->returnReceived('0.500001');
  }

  /**
   * Method testNegativeUnitCostIsRejected
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testNegativeUnitCostIsRejected(): void
  {
    $this->expectException(ProcurementException::class);

    ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '1', '-0.000001');
  }

  /**
   * Method testAmountsDoNotAcceptExponentCommaOrExcessPrecision
   *
   * @access public
   *
   * @param string $cost the malformed amount
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidCosts')]
  public function testAmountsDoNotAcceptExponentCommaOrExcessPrecision(string $cost): void
  {
    $this->expectException(InvalidValueException::class);

    ProcurementLine::create(self::LINE, 'part', self::PART, null, [], '1', $cost);
  }

  /**
   * Method invalidCosts
   *
   * @access public
   *
   * @return iterable<string, array{string}> malformed exact amounts
   */
  public static function invalidCosts(): iterable
  {
    yield 'scientific notation' => ['1e3'];
    yield 'comma separator' => ['12,5'];
    yield 'excess precision' => ['0.1234567'];
    yield 'whitespace' => [' 12.5'];
  }

  /**
   * Method testTemplatesAreDeclarativeAndBounded
   *
   * @access public
   *
   * @param array<string, mixed> $template the invalid identity payload
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidTemplates')]
  public function testTemplatesAreDeclarativeAndBounded(array $template): void
  {
    $this->expectException(ProcurementException::class);

    ProcurementLine::create(self::LINE, 'equipment_to_individualize', null, 'fire_extinguisher', $template, '1', null);
  }

  /**
   * Method invalidTemplates
   *
   * @access public
   *
   * @return iterable<string, array{array<string, mixed>}> unbounded or executable identity payloads
   */
  public static function invalidTemplates(): iterable
  {
    yield 'object value' => [['name' => new stdClass()]];
    yield 'nonfinite number' => [['capacity' => INF]];
    yield 'excessive depth' => [['a' => ['b' => ['c' => ['d' => ['e' => 'deep']]]]]];
    yield 'oversized string' => [['name' => str_repeat('x', 2001)]];
    yield 'blank key' => [[' ' => 'value']];
  }
  // #endregion
}
