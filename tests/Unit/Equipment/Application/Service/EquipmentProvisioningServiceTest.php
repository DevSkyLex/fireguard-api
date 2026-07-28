<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Service;

use DateTimeImmutable;
use Equipment\Application\Contract\Provisioning\{ProvisionEquipmentRequest, ProvisionOutcome};
use Equipment\Application\Service\EquipmentProvisioningService;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
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

    $result = new EquipmentProvisioningService($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertSame('equipment-1', $result->resourceId);
  }

  #[Test]
  public function itReturnsQuotaExceededWhenTheQuotaIsRaisedDirectly(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::EQUIPMENT, 5),
    );

    $result = new EquipmentProvisioningService($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
    self::assertNotNull($result->message);
  }

  #[Test]
  public function itUnwrapsAQuotaExceededExceptionFromAMessengerRuntimeException(): void
  {
    $quotaException = OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::EQUIPMENT, 5);
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($quotaException));

    $result = new EquipmentProvisioningService($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
  }

  #[Test]
  public function itReturnsInvalidForADuplicateSerialNumber(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      EquipmentSerialNumberAlreadyExistsException::withSerialNumber('SN-1'),
    );

    $result = new EquipmentProvisioningService($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
  }

  #[Test]
  public function itReturnsInvalidForAnInvalidArgumentWrappedByMessenger(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new InvalidArgumentException('Invalid equipment type.')),
    );

    $result = new EquipmentProvisioningService($commandBus)->provision($this->request());

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

    new EquipmentProvisioningService($commandBus)->provision($this->request());
  }

  #[Test]
  public function itReturnsInvalidForADuplicateSerialNumberWrappedByMessenger(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(EquipmentSerialNumberAlreadyExistsException::withSerialNumber('SN-42')),
    );

    $result = new EquipmentProvisioningService($commandBus)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertStringContainsString('SN-42', (string) $result->message);
  }

  private function request(): ProvisionEquipmentRequest
  {
    return new ProvisionEquipmentRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'fire_extinguisher',
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
