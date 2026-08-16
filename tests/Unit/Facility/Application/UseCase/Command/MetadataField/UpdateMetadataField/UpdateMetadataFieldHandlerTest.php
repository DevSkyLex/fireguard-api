<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\MetadataField\UpdateMetadataField;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Application\UseCase\Command\MetadataField\UpdateMetadataField\{UpdateMetadataFieldCommand, UpdateMetadataFieldHandler, UpdateMetadataFieldResult};
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
 * Test UpdateMetadataFieldHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateMetadataFieldHandler::class)]
final class UpdateMetadataFieldHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440300';

  private const string FIELD_ID = '660e8400-e29b-41d4-a716-446655440301';

  #[Test]
  public function testInvokeAppliesOnlyProvidedFields(): void
  {
    $field = $this->field();

    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($field);
    $repository->expects(self::once())->method('save');

    $handler = new UpdateMetadataFieldHandler($repository);

    $result = $handler->__invoke(new UpdateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      fieldId: self::FIELD_ID,
      label: 'Renamed label',
      hasLabel: true,
    ));

    self::assertInstanceOf(UpdateMetadataFieldResult::class, $result);
    self::assertSame('Renamed label', $result->label);
    self::assertSame('number', $result->fieldType);
  }

  #[Test]
  public function testInvokeThrowsWhenFieldNotFound(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn(null);
    $repository->expects(self::never())->method('save');

    $handler = new UpdateMetadataFieldHandler($repository);

    $this->expectException(FacilityMetadataFieldNotFoundException::class);

    $handler->__invoke(new UpdateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      fieldId: self::FIELD_ID,
      label: 'Whatever',
      hasLabel: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFieldBelongsToAnotherOrganization(): void
  {
    $field = $this->field();

    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($field);
    $repository->expects(self::never())->method('save');

    $handler = new UpdateMetadataFieldHandler($repository);

    $this->expectException(FacilityMetadataFieldNotFoundException::class);

    $handler->__invoke(new UpdateMetadataFieldCommand(
      organizationId: '660e8400-e29b-41d4-a716-446655440399',
      fieldId: self::FIELD_ID,
      label: 'Whatever',
      hasLabel: true,
    ));
  }

  #[Test]
  public function testInvokeChangesTypeAndOptionsTogether(): void
  {
    $field = $this->field();

    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($field);
    $repository->expects(self::once())->method('save');

    $handler = new UpdateMetadataFieldHandler($repository);

    $result = $handler->__invoke(new UpdateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      fieldId: self::FIELD_ID,
      fieldType: 'select',
      hasFieldType: true,
      options: ['A', 'B'],
      hasOptions: true,
    ));

    self::assertSame('select', $result->fieldType);
    self::assertSame(['A', 'B'], $result->options);
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
