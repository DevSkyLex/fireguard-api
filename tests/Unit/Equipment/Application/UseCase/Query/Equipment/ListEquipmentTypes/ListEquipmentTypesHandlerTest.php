<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\ListEquipmentTypes;

use Equipment\Application\UseCase\Query\Equipment\ListEquipmentTypes\{GetEquipmentTypeResult, ListEquipmentTypesHandler, ListEquipmentTypesQuery, ListEquipmentTypesResult};
use Equipment\Domain\ValueObject\EquipmentType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(ListEquipmentTypesHandler::class)]
final class ListEquipmentTypesHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsAllEquipmentTypes(): void
  {
    $handler = new ListEquipmentTypesHandler();

    $result = $handler->__invoke(new ListEquipmentTypesQuery('550e8400-e29b-41d4-a716-446655440001'));

    self::assertInstanceOf(ListEquipmentTypesResult::class, $result);
    self::assertCount(count(EquipmentType::cases()), $result->types);

    foreach ($result->types as $type) {
      self::assertInstanceOf(GetEquipmentTypeResult::class, $type);
      self::assertNotEmpty($type->value);
      self::assertNotEmpty($type->label);
    }
  }

  #[Test]
  public function testInvokeReturnsCorrectValuesAndLabels(): void
  {
    $handler = new ListEquipmentTypesHandler();

    $result = $handler->__invoke(new ListEquipmentTypesQuery('550e8400-e29b-41d4-a716-446655440001'));

    $byValue = [];
    foreach ($result->types as $type) {
      $byValue[$type->value] = $type->label;
    }

    self::assertSame('Fire Extinguisher', $byValue['fire_extinguisher']);
    self::assertSame('Smoke Detector', $byValue['smoke_detector']);
    self::assertSame('Other', $byValue['other']);
  }
}
