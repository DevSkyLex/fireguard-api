<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\{AssignToFacilityCommand, AssignToFacilityResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Input\Equipment\AssignToFacilityInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\AssignToFacilityProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(AssignToFacilityProcessor::class)]
final class AssignToFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsWrappedEquipmentNotFoundToHttp404(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441200';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441201';
    $facilityId = '550e8400-e29b-41d4-a716-446655441202';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441203');

    $input = new AssignToFacilityInput();
    $input->facilityId = $facilityId;

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

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new AssignToFacilityCommand(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        facilityId: $facilityId,
      )),
      exceptions: [EquipmentNotFoundException::withId($equipmentId)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new AssignToFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );
  }

  #[Test]
  public function testProcessReturnsEquipmentOutputOnSuccess(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441210';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441211';
    $facilityId = '550e8400-e29b-41d4-a716-446655441212';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441213');

    $input = new AssignToFacilityInput();
    $input->facilityId = $facilityId;
    $input->installedAt = '2026-01-15T09:00:00+00:00';

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
      ->willReturn(new AssignToFacilityResult(
        equipmentId: $equipmentId,
        organizationId: $organizationId,
        facilityId: $facilityId,
        type: 'fire_extinguisher',
        subType: null,
        brand: null,
        model: null,
        serialNumber: null,
        locationLabel: 'Floor 2, Room 201',
        status: 'in_stock',
        installedAt: '2026-01-15T09:00:00+00:00',
        commissionedAt: null,
        tags: [],
        createdAt: $now,
        updatedAt: $now,
      ));

    $processor = new AssignToFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame($facilityId, $output->facilityId);
    self::assertSame('Floor 2, Room 201', $output->locationLabel);
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
