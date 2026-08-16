<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField\{DeleteMetadataFieldCommand, DeleteMetadataFieldHandler, DeleteMetadataFieldResult};
use Facility\Domain\Exception\FacilityMetadataFieldNotFoundException;
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
 * Test DeleteMetadataFieldHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteMetadataFieldHandler::class)]
final class DeleteMetadataFieldHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440400';

  private const string FIELD_ID = '660e8400-e29b-41d4-a716-446655440401';

  #[Test]
  public function testInvokeDeletesTheDefinitionOnly(): void
  {
    $field = $this->field();

    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($field);
    $repository->expects(self::once())->method('delete')->with(self::callback(
      static fn (FacilityMetadataFieldId $id): bool => self::FIELD_ID === (string) $id,
    ));

    $handler = new DeleteMetadataFieldHandler($repository);

    $result = $handler->__invoke(new DeleteMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      fieldId: self::FIELD_ID,
    ));

    self::assertInstanceOf(DeleteMetadataFieldResult::class, $result);
    self::assertSame(self::FIELD_ID, $result->id);
  }

  #[Test]
  public function testInvokeThrowsWhenFieldNotFound(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn(null);
    $repository->expects(self::never())->method('delete');

    $handler = new DeleteMetadataFieldHandler($repository);

    $this->expectException(FacilityMetadataFieldNotFoundException::class);

    $handler->__invoke(new DeleteMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      fieldId: self::FIELD_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFieldBelongsToAnotherOrganization(): void
  {
    $field = $this->field();

    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($field);
    $repository->expects(self::never())->method('delete');

    $handler = new DeleteMetadataFieldHandler($repository);

    $this->expectException(FacilityMetadataFieldNotFoundException::class);

    $handler->__invoke(new DeleteMetadataFieldCommand(
      organizationId: '660e8400-e29b-41d4-a716-446655440499',
      fieldId: self::FIELD_ID,
    ));
  }

  private function field(): FacilityMetadataField
  {
    return FacilityMetadataField::reconstitute(
      id: FacilityMetadataFieldId::fromString(self::FIELD_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      key: new FacilityMetadataFieldKey('surface-m2'),
      label: new FacilityMetadataFieldLabel('Surface (m²)'),
      fieldType: FacilityMetadataFieldType::NUMBER,
      required: false,
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
    );
  }
}
