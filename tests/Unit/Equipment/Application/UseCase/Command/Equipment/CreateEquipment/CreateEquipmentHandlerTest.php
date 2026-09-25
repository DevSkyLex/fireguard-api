<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\CreateEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort, FacilityValidationPort};
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentHandler, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use Onboarding\Application\Contract\Setup\{OrganizationSetupContext, OrganizationSetupOperation};
use Onboarding\Application\Port\Inbound\OrganizationSetupPort;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(CreateEquipmentHandler::class)]
final class CreateEquipmentHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidType(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(EquipmentId::class)
      ->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440900'));

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440981',
      type: 'invalid_equipment_type',
    ));
  }

  #[Test]
  public function testInvokeThrowsSerialNumberAlreadyExistsOnUniqueConstraintViolation(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(EquipmentSerialNumberAlreadyExistsException::withSerialNumber('EXT-2026-001'));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(EquipmentId::class)
      ->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440900'));

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(EquipmentSerialNumberAlreadyExistsException::class);
    $this->expectExceptionMessage('Serial number "EXT-2026-001" already exists in this organization.');

    $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440982',
      type: 'fire_extinguisher',
      serialNumber: 'EXT-2026-001',
    ));
  }

  #[Test]
  public function testInvokeReturnsResultOnSuccess(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(EquipmentId::class)
      ->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440903'));

    $handler = $this->handler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440983',
      type: 'fire_extinguisher',
      brand: 'Sicli',
      model: 'Pro 6',
    ));

    self::assertInstanceOf(CreateEquipmentResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655440903', $result->equipmentId);
    self::assertSame('fire_extinguisher', $result->type);
    self::assertSame('Sicli', $result->brand);
    self::assertNull($result->facilityName);
    self::assertSame('in_stock', $result->status);
  }

  #[Test]
  public function testInvokeThrowsAndSkipsSaveWhenQuotaExceeded(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(EquipmentId::class)
      ->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440904'));

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAdd')
      ->with('550e8400-e29b-41d4-a716-446655440984', OrganizationQuotaResource::EQUIPMENT)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::EQUIPMENT->value, 50));

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440984',
      type: 'fire_extinguisher',
    ));
  }

  #[Test]
  public function testInvokeReturnsResultWithoutPersistingOnADryRun(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440986';

    // The negative assertion is the point: a dry run must never reach the
    // repository or the transaction manager.
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440906'));

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');
    $quota->expects(self::once())
      ->method('assertProjectedCanAdd')
      ->with($organizationId, OrganizationQuotaResource::EQUIPMENT, 0);

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $result = $handler->__invoke(new CreateEquipmentCommand(
      organizationId: $organizationId,
      type: 'fire_extinguisher',
      dryRun: true,
    ));

    self::assertInstanceOf(CreateEquipmentResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655440906', $result->equipmentId);
    self::assertSame('in_stock', $result->status);
  }

  #[Test]
  public function testInvokeThrowsQuotaExceededOnADryRunWhenTheProjectedCountReachesTheCap(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440987';

    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440907'));

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');
    $quota->expects(self::once())
      ->method('assertProjectedCanAdd')
      ->with($organizationId, OrganizationQuotaResource::EQUIPMENT, 2)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::EQUIPMENT->value, 10));

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new CreateEquipmentCommand(
      organizationId: $organizationId,
      type: 'fire_extinguisher',
      dryRun: true,
      quotaProjectionOffset: 2,
    ));
  }

  #[Test]
  public function testInvokeHonoursAClientSuppliedResourceIdInsteadOfMintingOne(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = $this->handler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440985',
      type: 'fire_extinguisher',
      resourceId: '550e8400-e29b-41d4-a716-446655440905',
    ));

    self::assertInstanceOf(CreateEquipmentResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655440905', $result->equipmentId);
  }

  #[Test]
  public function itPersistsTheFacilityAssignmentWithCreation(): void
  {
    $facilityId = '550e8400-e29b-41d4-a716-446655440990';
    $organizationId = '550e8400-e29b-41d4-a716-446655440981';
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('save')->with(self::callback(
      static fn (Equipment $equipment): bool => (string) $equipment->facilityId() === $facilityId,
    ));
    $validation = $this->createMock(FacilityValidationPort::class);
    $validation->expects(self::once())->method('assertFacilityIsAssignable')->with($facilityId, $organizationId);

    $result = ($this->handler($repository, $this->createStub(UuidFactory::class), facilityValidation: $validation))(new CreateEquipmentCommand(
      organizationId: $organizationId,
      type: 'fire_extinguisher',
      resourceId: '550e8400-e29b-41d4-a716-446655440905',
      facilityId: $facilityId,
    ));

    self::assertSame($facilityId, $result->facilityId);
  }

  #[Test]
  public function itNeverPersistsEquipmentWhenItsFacilityIsNoLongerAssignable(): void
  {
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $validation = $this->createStub(FacilityValidationPort::class);
    $validation->method('assertFacilityIsAssignable')->willThrowException(new InvalidArgumentException('Facility is archived.'));
    $this->expectException(InvalidArgumentException::class);

    ($this->handler($repository, $this->createStub(UuidFactory::class), facilityValidation: $validation))(new CreateEquipmentCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440981',
      type: 'fire_extinguisher',
      resourceId: '550e8400-e29b-41d4-a716-446655440905',
      facilityId: '550e8400-e29b-41d4-a716-446655440990',
    ));
  }

  #[Test]
  public function itCompletesTheSetupReceiptInsideTheCreationTransaction(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440981';
    $equipmentId = '550e8400-e29b-41d4-a716-446655440905';
    $facilityId = '550e8400-e29b-41d4-a716-446655440990';
    $context = new OrganizationSetupContext('user-id', 'session-id', 'first-item');
    $calls = [];

    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())->method('transactional')->willReturnCallback(
      static function (callable $operation) use (&$calls): mixed {
        $calls[] = 'transaction.begin';
        $result = $operation();
        $calls[] = 'transaction.end';

        return $result;
      },
    );

    $setup = $this->createMock(OrganizationSetupPort::class);
    $setup->expects(self::once())->method('begin')->willReturnCallback(
      static function (OrganizationSetupContext $actualContext, string $step, ?string $actualOrganizationId, array $payload) use (&$calls, $context, $organizationId, $facilityId): OrganizationSetupOperation {
        $calls[] = 'receipt.begin';
        self::assertSame($context, $actualContext);
        self::assertSame('create_first_equipment', $step);
        self::assertSame($organizationId, $actualOrganizationId);
        self::assertSame('/api/facilities/' . $facilityId, $payload['facility']);

        return new OrganizationSetupOperation($step, $context->itemKey, []);
      },
    );
    $setup->expects(self::once())->method('complete')->willReturnCallback(
      static function (OrganizationSetupContext $actualContext, string $step, string $resourceId) use (&$calls, $context, $equipmentId): void {
        $calls[] = 'receipt.complete';
        self::assertSame($context, $actualContext);
        self::assertSame('create_first_equipment', $step);
        self::assertSame($equipmentId, $resourceId);
      },
    );

    $validation = $this->createMock(FacilityValidationPort::class);
    $validation->expects(self::once())->method('assertFacilityIsAssignable')->willReturnCallback(
      static function (string $actualFacilityId, string $actualOrganizationId) use (&$calls, $facilityId, $organizationId): void {
        $calls[] = 'facility.validate';
        self::assertSame($facilityId, $actualFacilityId);
        self::assertSame($organizationId, $actualOrganizationId);
      },
    );

    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())->method('assertCanAdd')->willReturnCallback(
      static function (string $actualOrganizationId, OrganizationQuotaResource $resource) use (&$calls, $organizationId): void {
        $calls[] = 'quota';
        self::assertSame($organizationId, $actualOrganizationId);
        self::assertSame(OrganizationQuotaResource::EQUIPMENT, $resource);
      },
    );

    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willReturnCallback(
      static function (Equipment $equipment) use (&$calls, $facilityId): void {
        $calls[] = 'save';
        self::assertSame($facilityId, (string) $equipment->facilityId());
      },
    );

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new EquipmentId($equipmentId));

    $result = ($this->handler($repository, $uuidFactory, $quota, $validation, $setup, $transactionManager))(
      new CreateEquipmentCommand(
        organizationId: $organizationId,
        type: 'fire_extinguisher',
        resourceId: null,
        setupContext: $context,
        facilityId: $facilityId,
      ),
    );

    self::assertSame($equipmentId, $result->equipmentId);
    self::assertSame(['transaction.begin', 'receipt.begin', 'facility.validate', 'quota', 'save', 'receipt.complete', 'transaction.end'], $calls);
  }

  #[Test]
  public function itReplaysAnExistingSetupReceiptWithoutCreatingAnotherEquipment(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440981';
    $equipmentId = '550e8400-e29b-41d4-a716-446655440905';
    $context = new OrganizationSetupContext('user-id', 'session-id', 'first-item');
    $existing = Equipment::create(
      new EquipmentId($equipmentId),
      new EquipmentOrganizationId($organizationId),
      EquipmentType::FIRE_EXTINGUISHER,
    );

    $setup = $this->createMock(OrganizationSetupPort::class);
    $setup->expects(self::once())->method('begin')->willReturn(new OrganizationSetupOperation(
      'create_first_equipment',
      $context->itemKey,
      [],
      $equipmentId,
    ));
    $setup->expects(self::never())->method('complete');

    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->with(self::callback(
      static fn (EquipmentId $id): bool => (string) $id === $equipmentId,
    ))->willReturn($existing);
    $repository->expects(self::never())->method('save');

    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new EquipmentId('550e8400-e29b-41d4-a716-446655440906'));

    $result = ($this->handler($repository, $uuidFactory, $quota, setup: $setup))(
      new CreateEquipmentCommand($organizationId, 'fire_extinguisher', setupContext: $context),
    );

    self::assertSame($equipmentId, $result->equipmentId);
  }

  /**
   * Builds the handler with a pass-through transaction manager (invokes the
   * operation inline) and a permissive quota port unless one is supplied.
   */
  private function handler(
    EquipmentRepositoryPort $repository,
    UuidFactory $uuidFactory,
    ?OrganizationQuotaPort $quota = null,
    ?FacilityValidationPort $facilityValidation = null,
    ?OrganizationSetupPort $setup = null,
    ?TransactionManagerPort $transactionManager = null,
  ): CreateEquipmentHandler {
    if (null === $transactionManager) {
      $transactionManager = $this->createStub(TransactionManagerPort::class);
      $transactionManager->method('transactional')->willReturnCallback(
        static fn (callable $operation): mixed => $operation(),
      );
    }

    return new CreateEquipmentHandler(
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      equipmentRepository: $repository,
      uuidFactory: $uuidFactory,
      quota: $quota ?? $this->createStub(OrganizationQuotaPort::class),
      transactionManager: $transactionManager,
      facilityValidation: $facilityValidation,
      setup: $setup,
    );
  }
}
