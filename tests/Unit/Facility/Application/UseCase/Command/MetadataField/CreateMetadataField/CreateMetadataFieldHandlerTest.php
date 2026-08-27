<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\MetadataField\CreateMetadataField;

use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Application\UseCase\Command\MetadataField\CreateMetadataField\{CreateMetadataFieldCommand, CreateMetadataFieldHandler, CreateMetadataFieldResult};
use Facility\Domain\Exception\{FacilityMetadataFieldKeyAlreadyExistsException, FacilityMetadataFieldLimitExceededException};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test CreateMetadataFieldHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateMetadataFieldHandler::class)]
final class CreateMetadataFieldHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655440200';

  private const string GENERATED_ID = '660e8400-e29b-41d4-a716-446655440201';

  #[Test]
  public function testInvokeReturnsResult(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('countByOrganizationId')->willReturn(0);
    $repository->expects(self::once())->method('findByOrganizationIdAndKey')->willReturn(null);
    $repository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(\Facility\Domain\ValueObject\FacilityMetadataFieldId::fromString(self::GENERATED_ID));

    $handler = new CreateMetadataFieldHandler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      key: 'surface-m2',
      label: 'Surface (m²)',
      fieldType: 'number',
      required: true,
      unit: 'm²',
    ));

    self::assertInstanceOf(CreateMetadataFieldResult::class, $result);
    self::assertSame(self::GENERATED_ID, $result->id);
    self::assertSame('surface-m2', $result->key);
    self::assertSame('number', $result->fieldType);
    self::assertTrue($result->required);
    self::assertSame('m²', $result->unit);
  }

  #[Test]
  public function testInvokeThrowsWhenAtTheCap(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('countByOrganizationId')->willReturn(50);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(\Facility\Domain\ValueObject\FacilityMetadataFieldId::fromString(self::GENERATED_ID));

    $handler = new CreateMetadataFieldHandler($repository, $uuidFactory);

    $this->expectException(FacilityMetadataFieldLimitExceededException::class);

    $handler->__invoke(new CreateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      key: 'one-too-many',
      label: 'One too many',
      fieldType: 'text',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenKeyAlreadyExists(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::once())->method('countByOrganizationId')->willReturn(1);
    $repository->expects(self::once())->method('findByOrganizationIdAndKey')->willReturn(
      \Facility\Domain\Model\MetadataField\FacilityMetadataField::create(
        id: \Facility\Domain\ValueObject\FacilityMetadataFieldId::fromString(self::GENERATED_ID),
        organizationId: new \Facility\Domain\ValueObject\FacilityOrganizationId(self::ORGANIZATION_ID),
        key: new \Facility\Domain\ValueObject\FacilityMetadataFieldKey('surface-m2'),
        label: new \Facility\Domain\ValueObject\FacilityMetadataFieldLabel('Surface'),
        fieldType: \Facility\Domain\ValueObject\FacilityMetadataFieldType::NUMBER,
      ),
    );
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(\Facility\Domain\ValueObject\FacilityMetadataFieldId::fromString(self::GENERATED_ID));

    $handler = new CreateMetadataFieldHandler($repository, $uuidFactory);

    $this->expectException(FacilityMetadataFieldKeyAlreadyExistsException::class);

    $handler->__invoke(new CreateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      key: 'surface-m2',
      label: 'Surface again',
      fieldType: 'number',
    ));
  }

  #[Test]
  public function testInvokeThrowsOnInvalidKeyFormat(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);

    $handler = new CreateMetadataFieldHandler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      key: 'Not A Valid Key',
      label: 'Whatever',
      fieldType: 'text',
    ));
  }

  #[Test]
  public function testInvokeThrowsOnSelectWithoutOptions(): void
  {
    /** @var FacilityMetadataFieldRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityMetadataFieldRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(\Facility\Domain\ValueObject\FacilityMetadataFieldId::fromString(self::GENERATED_ID));

    $handler = new CreateMetadataFieldHandler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateMetadataFieldCommand(
      organizationId: self::ORGANIZATION_ID,
      key: 'building-category',
      label: 'Building category',
      fieldType: 'select',
    ));
  }
}
