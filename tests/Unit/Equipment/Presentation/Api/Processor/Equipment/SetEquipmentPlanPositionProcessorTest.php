<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Put;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\Contract\FloorPlan\{
  FloorPlanAttachmentNotAncestorException,
  FloorPlanAttachmentNotFloorPlanException,
  FloorPlanAttachmentNotFoundException
};
use Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\SetEquipmentPlanPositionCommand;
use Equipment\Domain\Exception\{
  EquipmentAlreadyDecommissionedException,
  EquipmentNotAssignedToFacilityException,
  EquipmentNotFoundException
};
use Equipment\Presentation\Api\Dto\Input\Equipment\SetEquipmentPlanPositionInput;
use Equipment\Presentation\Api\Processor\Equipment\SetEquipmentPlanPositionProcessor;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(SetEquipmentPlanPositionProcessor::class)]
final class SetEquipmentPlanPositionProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440003';

  /**
   * @return iterable<string, array{Throwable, int, bool}>
   */
  public static function mappedFailures(): iterable
  {
    $failures = [
      'equipment missing' => [EquipmentNotFoundException::withId(self::EQUIPMENT_ID), 404],
      'attachment missing' => [FloorPlanAttachmentNotFoundException::withId(self::ATTACHMENT_ID), 404],
      'decommissioned' => [EquipmentAlreadyDecommissionedException::withId(self::EQUIPMENT_ID), 409],
      'unassigned' => [EquipmentNotAssignedToFacilityException::withId(self::EQUIPMENT_ID), 409],
      'not a floor plan' => [FloorPlanAttachmentNotFloorPlanException::forAttachment(self::ATTACHMENT_ID), 409],
      'outside facility ancestry' => [FloorPlanAttachmentNotAncestorException::forAttachment(self::ATTACHMENT_ID, 'facility-id'), 409],
      'invalid position' => [new InvalidArgumentException('Position coordinates are invalid.'), 400],
    ];

    foreach ($failures as $name => [$failure, $status]) {
      yield $name . ' direct' => [$failure, $status, false];
      yield $name . ' wrapped' => [$failure, $status, true];
    }
  }

  #[Test]
  #[DataProvider('mappedFailures')]
  public function testProcessPreservesDomainFailureStatusAndCause(Throwable $failure, int $status, bool $wrapped): void
  {
    $thrown = $wrapped ? self::wrapped($failure) : $failure;

    try {
      $this->processorThrowing($thrown)->process(
        new SetEquipmentPlanPositionInput(),
        new Put(),
        ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIPMENT_ID],
      );
    } catch (Throwable $mapped) {
      self::assertInstanceOf(HttpExceptionInterface::class, $mapped);
      self::assertSame($status, $mapped->getStatusCode());
      self::assertSame($failure->getMessage(), $mapped->getMessage());
      self::assertSame($thrown, $mapped->getPrevious());

      return;
    }

    self::fail('Expected an HTTP exception.');
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $wrapped = self::wrapped(new RuntimeException('Storage failed.'));

    $this->expectExceptionObject($wrapped);

    $this->processorThrowing($wrapped)->process(
      new SetEquipmentPlanPositionInput(),
      new Put(),
      ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIPMENT_ID],
    );
  }

  private function processorThrowing(Throwable $failure): SetEquipmentPlanPositionProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: '550e8400-e29b-41d4-a716-446655440004',
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return new SetEquipmentPlanPositionProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      detail: \Tests\Support\MutationDetailFixtures::equipment(null),
    );
  }

  private static function wrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new SetEquipmentPlanPositionCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIPMENT_ID,
        attachmentId: self::ATTACHMENT_ID,
        x: 0.2,
        y: 0.4,
      )),
      [$failure],
    ));
  }
}
