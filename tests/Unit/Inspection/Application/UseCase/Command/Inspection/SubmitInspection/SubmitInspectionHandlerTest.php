<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\SubmitInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Application\UseCase\Command\Inspection\SubmitInspection\{
  SubmitInspectionCommand,
  SubmitInspectionHandler,
  SubmitInspectionResult
};
use Inspection\Domain\Event\Inspection\InspectionSubmittedEvent;
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus,
  Inspector
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test SubmitInspectionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SubmitInspectionHandler::class)]
final class SubmitInspectionHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';
  // #endregion

  // #region Methods
  #[Test]
  public function testInvokeSubmitsInspectionAndReturnsResult(): void
  {
    $inspection = $this->makeDraftInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($inspection);
    $repository->expects(self::once())
      ->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof InspectionSubmittedEvent
          && self::ORG_ID === $event->organizationId
          && self::INSP_ID === $event->inspectionId
          && self::EQUIP_ID === $event->equipmentId
          && InspectionResult::PASS->value === $event->result,
      ));

    $handler = new SubmitInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new SubmitInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));

    self::assertInstanceOf(SubmitInspectionResult::class, $result);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame(InspectionStatus::SUBMITTED->value, $result->status);
    self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SubmitInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new SubmitInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationMismatch(): void
  {
    $inspection = $this->makeDraftInspection();

    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SubmitInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new SubmitInspectionCommand(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      inspectionId: self::INSP_ID,
    ));
  }

  // #region Helpers
  #[Test]
  public function testInvokeRejectsAMalformedIdentifier(): void
  {
    $handler = new SubmitInspectionHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new SubmitInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: 'not-a-uuid',
    ));
  }

  private function makeDraftInspection(): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }
  // #endregion
}
