<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Service;

use DateTimeImmutable;
use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionOutcome};
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Application\Service\EquipmentProvisioningService;
use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\AssignToFacilityCommand;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use InvalidArgumentException;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test EquipmentProvisioningServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentProvisioningService::class)]
final class EquipmentProvisioningServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f68a01';

  #[Test]
  public function itReturnsCreatedWithTheResourceIdOnSuccess(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(CreateEquipmentCommand::class))
      ->willReturn($this->fakeResult('equipment-1'));

    $result = $this->service($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertSame('equipment-1', $result->resourceId);
  }

  #[Test]
  public function itReturnsQuotaExceededWhenTheQuotaIsRaisedDirectly(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::EQUIPMENT->value, 5),
    );

    $result = $this->service($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
    self::assertNotNull($result->message);
  }

  #[Test]
  public function itUnwrapsAQuotaExceededExceptionFromAMessengerRuntimeException(): void
  {
    $quotaException = OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::EQUIPMENT->value, 5);
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($quotaException));

    $result = $this->service($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
  }

  #[Test]
  public function itReturnsInvalidForADuplicateSerialNumber(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      EquipmentSerialNumberAlreadyExistsException::withSerialNumber('SN-1'),
    );

    $result = $this->service($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
  }

  #[Test]
  public function itReturnsInvalidForAnInvalidArgumentWrappedByMessenger(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Invalid equipment type.')),
    );

    $result = $this->service($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('Invalid equipment type.', $result->message);
  }

  #[Test]
  public function itRethrowsAnUnrecognizedWrappedException(): void
  {
    $wrapped = MessengerRuntimeException::wrap(new RuntimeException('unexpected'));
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($wrapped);

    $this->expectException(MessengerRuntimeException::class);

    $this->service($commandBus)->provision($this->request());
  }

  #[Test]
  public function itReturnsInvalidForADuplicateSerialNumberWrappedByMessenger(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(EquipmentSerialNumberAlreadyExistsException::withSerialNumber('SN-42')),
    );

    $result = $this->service($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertStringContainsString('SN-42', (string) $result->message);
  }

  #[Test]
  public function itPassesDryRunAndTheQuotaProjectionOffsetThrough(): void
  {
    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CreateEquipmentCommand $command) use (&$captured): CreateEquipmentResult {
        $captured = $command;

        return $this->fakeResult('equipment-2');
      });

    $request = new ProvisionEquipmentRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'fire_extinguisher',
      dryRun: true,
      quotaProjectionOffset: 4,
    );

    $this->service($commandBus)->provision($request);

    self::assertInstanceOf(CreateEquipmentCommand::class, $captured);
    self::assertTrue($captured->dryRun);
    self::assertSame(4, $captured->quotaProjectionOffset);
  }

  #[Test]
  public function itReturnsInvalidWithoutDispatchingWhenTheFacilityCodeIsUnknown(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('resolveIdByCode')
      ->with(self::ORGANIZATION_ID, 'NOPE-01')
      ->willReturn(null);

    $result = $this->service($commandBus, $facilityValidation)
      ->provision($this->request(facilityCode: 'NOPE-01'));

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertStringContainsString('NOPE-01', (string) $result->message);
  }

  #[Test]
  public function itAssignsTheCreatedEquipmentWhenTheFacilityCodeResolves(): void
  {
    $dispatched = [];
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function (object $command) use (&$dispatched): CreateEquipmentResult {
        $dispatched[] = $command;

        return $this->fakeResult('equipment-9');
      });

    $facilityValidation = $this->createStub(FacilityValidationPort::class);
    $facilityValidation->method('resolveIdByCode')->willReturn('facility-7');

    $result = $this->service($commandBus, $facilityValidation)
      ->provision($this->request(facilityCode: 'WH-01'));

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertSame('equipment-9', $result->resourceId);
    self::assertInstanceOf(CreateEquipmentCommand::class, $dispatched[0]);
    self::assertInstanceOf(AssignToFacilityCommand::class, $dispatched[1]);
    self::assertSame('equipment-9', $dispatched[1]->equipmentId);
    self::assertSame('facility-7', $dispatched[1]->facilityId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched[1]->organizationId);
  }

  #[Test]
  public function itReportsInvalidWithTheCreatedIdWhenTheAssignmentFailsAfterCreation(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function (object $command): CreateEquipmentResult {
        if ($command instanceof AssignToFacilityCommand) {
          throw MessengerRuntimeException::wrap(new InvalidArgumentException('Facility with ID "facility-7" is archived and cannot be used.'));
        }

        return $this->fakeResult('equipment-9');
      });

    $facilityValidation = $this->createStub(FacilityValidationPort::class);
    $facilityValidation->method('resolveIdByCode')->willReturn('facility-7');

    $result = $this->service($commandBus, $facilityValidation)
      ->provision($this->request(facilityCode: 'WH-01'));

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('equipment-9', $result->resourceId, 'The created-but-unassigned equipment id must be reported.');
    self::assertStringContainsString('created but could not be assigned', (string) $result->message);
    self::assertStringContainsString('archived', (string) $result->message);
  }

  #[Test]
  public function itResolvesTheFacilityCodeButNeverAssignsOnADryRun(): void
  {
    $dispatched = [];
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (object $command) use (&$dispatched): CreateEquipmentResult {
        $dispatched[] = $command;

        return $this->fakeResult('equipment-9');
      });

    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('resolveIdByCode')
      ->with(self::ORGANIZATION_ID, 'WH-01')
      ->willReturn('facility-7');

    $request = new ProvisionEquipmentRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'fire_extinguisher',
      facilityCode: 'WH-01',
      dryRun: true,
    );

    $result = $this->service($commandBus, $facilityValidation)->provision($request);

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertInstanceOf(CreateEquipmentCommand::class, $dispatched[0]);
  }

  #[Test]
  public function itDetectsAnUnknownFacilityCodeOnADryRunToo(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $facilityValidation = $this->createStub(FacilityValidationPort::class);
    $facilityValidation->method('resolveIdByCode')->willReturn(null);

    $request = new ProvisionEquipmentRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'fire_extinguisher',
      facilityCode: 'NOPE-01',
      dryRun: true,
    );

    $result = $this->service($commandBus, $facilityValidation)->provision($request);

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
  }

  private function service(CommandBusPort $commandBus, ?FacilityValidationPort $facilityValidation = null): EquipmentProvisioningService
  {
    return new EquipmentProvisioningService(
      $commandBus,
      $facilityValidation ?? $this->createStub(FacilityValidationPort::class),
    );
  }

  private function request(?string $facilityCode = null): ProvisionEquipmentRequest
  {
    return new ProvisionEquipmentRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'fire_extinguisher',
      facilityCode: $facilityCode,
    );
  }

  private function fakeResult(string $equipmentId): CreateEquipmentResult
  {
    return new CreateEquipmentResult(
      equipmentId: $equipmentId,
      organizationId: self::ORGANIZATION_ID,
      facilityId: null,
      type: 'fire_extinguisher',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: 'in_stock',
      installedAt: null,
      commissionedAt: null,
      tags: [],
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
    );
  }
}
