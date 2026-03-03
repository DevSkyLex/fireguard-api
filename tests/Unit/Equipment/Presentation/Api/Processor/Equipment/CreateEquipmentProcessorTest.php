<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\EquipmentSerialNumberAlreadyExistsException;
use Equipment\Presentation\Api\Dto\Input\Equipment\CreateEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\CreateEquipmentProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(CreateEquipmentProcessor::class)]
final class CreateEquipmentProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsWrappedSerialNumberConflictToHttp409(): void
  {
    $input = new CreateEquipmentInput();
    $input->type = 'fire_extinguisher';
    $input->serialNumber = 'EXT-2026-001';

    $organizationId = '550e8400-e29b-41d4-a716-446655441100';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441101');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.write')
      ->willReturn(true);

    $domainException = EquipmentSerialNumberAlreadyExistsException::withSerialNumber('EXT-2026-001');
    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateEquipmentCommand(
        organizationId: $organizationId,
        type: 'fire_extinguisher',
        serialNumber: 'EXT-2026-001',
      )),
      [$domainException],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Serial number "EXT-2026-001" already exists in this organization.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => $organizationId],
    );
  }

  #[Test]
  public function testProcessReturnsEquipmentOutputOnSuccess(): void
  {
    $input = new CreateEquipmentInput();
    $input->type = 'smoke_detector';
    $input->brand = 'Ei Electronics';

    $organizationId = '550e8400-e29b-41d4-a716-446655441102';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441103';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441104');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new CreateEquipmentResult(
        equipmentId: $equipmentId,
        organizationId: $organizationId,
        facilityId: null,
        type: 'smoke_detector',
        subType: null,
        brand: 'Ei Electronics',
        model: null,
        serialNumber: null,
        locationLabel: null,
        status: 'in_stock',
        installedAt: null,
        commissionedAt: null,
        tags: [],
        createdAt: $now,
        updatedAt: $now,
      ));

    $processor = new CreateEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => $organizationId],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame($equipmentId, $output->id);
    self::assertSame('smoke_detector', $output->type);
    self::assertSame('Ei Electronics', $output->brand);
    self::assertSame('in_stock', $output->status);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
