<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\MetadataField\ListMetadataFields;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Application\UseCase\Query\MetadataField\ListMetadataFields\{ListMetadataFieldsHandler, ListMetadataFieldsQuery, ListMetadataFieldsResult};
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ListMetadataFieldsHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListMetadataFieldsHandler::class)]
final class ListMetadataFieldsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440500';

  #[Test]
  public function testInvokeReturnsEveryDefinitionForTheOrganization(): void
  {
    $field = FacilityMetadataField::reconstitute(
      id: FacilityMetadataFieldId::fromString('660e8400-e29b-41d4-a716-446655440501'),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      key: new FacilityMetadataFieldKey('surface-m2'),
      label: new FacilityMetadataFieldLabel('Surface (m²)'),
      fieldType: FacilityMetadataFieldType::NUMBER,
      required: true,
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
      unit: 'm²',
    );

    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findByOrganizationId')->willReturn([$field]);

    $handler = new ListMetadataFieldsHandler($repository);

    $result = $handler->__invoke(new ListMetadataFieldsQuery(organizationId: self::ORGANIZATION_ID));

    self::assertInstanceOf(ListMetadataFieldsResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame('surface-m2', $result->items[0]->key);
    self::assertSame('m²', $result->items[0]->unit);
    self::assertTrue($result->items[0]->required);
  }

  #[Test]
  public function testInvokeReturnsEmptyListWhenNoDefinitionsExist(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findByOrganizationId')->willReturn([]);

    $handler = new ListMetadataFieldsHandler($repository);

    $result = $handler->__invoke(new ListMetadataFieldsQuery(organizationId: self::ORGANIZATION_ID));

    self::assertSame([], $result->items);
  }
}
