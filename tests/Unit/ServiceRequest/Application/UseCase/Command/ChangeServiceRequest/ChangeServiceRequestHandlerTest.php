<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceRequest\Application\UseCase\Command\ChangeServiceRequest;

use DateTimeImmutable;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ServiceRequest\Application\Contract\Source\ServiceRequestOriginUnavailable;
use ServiceRequest\Application\Contract\Target\{ServiceRequestEquipmentTarget, ServiceRequestSiteTarget};
use ServiceRequest\Application\Port\Outbound\{ServiceRequestEquipmentTargetPort, ServiceRequestOriginPort, ServiceRequestRepositoryPort, ServiceRequestSiteTargetPort};
use ServiceRequest\Application\Service\{ServiceRequestAccessGuard, ServiceRequestTargetGuard};
use ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\{ChangeServiceRequestCommand, ChangeServiceRequestHandler};
use ServiceRequest\Domain\Event\ServiceRequestChangedEvent;
use ServiceRequest\Domain\Exception\ServiceRequestException;
use ServiceRequest\Domain\Model\ServiceRequest\ServiceRequest;
use ServiceRequest\Domain\ValueObject\{ServiceRequestContent, ServiceRequestTarget};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort};

/**
 * Qualification preserves the reported site/customer identity and revision boundaries.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChangeServiceRequestHandlerTest extends TestCase
{
  private const string REQUEST = '9b4e8400-e29b-41d4-a716-4466559b4001';

  private const string ORGANIZATION = '9b4e8400-e29b-41d4-a716-4466559b4002';

  private const string EQUIPMENT = '9b4e8400-e29b-41d4-a716-4466559b4003';

  private const string SITE = '9b4e8400-e29b-41d4-a716-4466559b4004';

  private const string CUSTOMER = '9b4e8400-e29b-41d4-a716-4466559b4005';

  private const string INSPECTION = '9b4e8400-e29b-41d4-a716-4466559b4006';

  private const string ACTOR = '9b4e8400-e29b-41d4-a716-4466559b4007';

  private const string OTHER_EQUIPMENT = '9b4e8400-e29b-41d4-a716-4466559b4008';

  private bool $inTransaction = false;

  #[Test]
  public function siteQualificationAddsEquipmentAndPreservesReportedSiteCustomer(): void
  {
    $request = $this->request(null);
    $requests = $this->createMock(ServiceRequestRepositoryPort::class);
    $requests->expects(self::once())->method('find')->with(self::REQUEST, self::ORGANIZATION, true)->willReturn($request);
    $requests->expects(self::once())->method('save')->with(self::callback(function (ServiceRequest $saved) use ($request): bool {
      self::assertTrue($this->inTransaction);
      self::assertSame('qualified', $saved->status);
      self::assertSame(3, $saved->revision);
      self::assertSame(self::EQUIPMENT, $saved->equipmentId);
      self::assertSame($request->siteId, $saved->siteId);
      self::assertSame($request->targetSnapshot['site'], $saved->targetSnapshot['site']);
      self::assertSame($request->targetSnapshot['customer'], $saved->targetSnapshot['customer']);
      self::assertSame(['id' => self::EQUIPMENT, 'name' => 'Extincteur entrée', 'assetCode' => 'EXT-001', 'status' => 'active'], $saved->targetSnapshot['equipment']);
      self::assertSame('Réparer la poignée.', $saved->qualificationNote);
      self::assertSame(self::INSPECTION, $saved->originInspectionId);

      return true;
    }), 1);
    $equipment = $this->createMock(ServiceRequestEquipmentTargetPort::class);
    $equipment->expects(self::once())->method('find')->with(self::EQUIPMENT, self::ORGANIZATION)->willReturn(new ServiceRequestEquipmentTarget(self::EQUIPMENT, 'Extincteur entrée', 'EXT-001', 'active', self::SITE));
    $sites = $this->createMock(ServiceRequestSiteTargetPort::class);
    $sites->expects(self::once())->method('lock')->with(self::ORGANIZATION);
    $sites->expects(self::once())->method('find')->with(self::ORGANIZATION, self::SITE, self::SITE)->willReturn(new ServiceRequestSiteTarget(self::SITE, 'Renamed site', false, ['id' => self::CUSTOMER, 'name' => 'Renamed customer']));
    $origins = $this->createMock(ServiceRequestOriginPort::class);
    $origins->expects(self::once())->method('assertMatches')->with(self::ORGANIZATION, self::EQUIPMENT, self::SITE, self::INSPECTION, null);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(function (object $event): bool {
      self::assertTrue($this->inTransaction);
      self::assertInstanceOf(ServiceRequestChangedEvent::class, $event);
      self::assertSame(self::REQUEST, $event->requestId);
      self::assertSame('qualify', $event->change);
      self::assertSame(3, $event->revision);

      return true;
    }));

    $result = $this->handler($requests, $origins, $equipment, $sites, $events)(new ChangeServiceRequestCommand(self::ACTOR, self::ORGANIZATION, self::REQUEST, 'qualify', 1, note: ' Réparer la poignée. ', equipmentId: self::EQUIPMENT));

    self::assertSame('qualified', $result->request->status);
    self::assertSame(['id' => self::CUSTOMER, 'name' => 'Reported customer'], $result->request->targetSnapshot['customer']);
    self::assertSame(['id' => self::SITE, 'name' => 'Reported site'], $result->request->targetSnapshot['site']);
    self::assertFalse($this->inTransaction);
  }

  #[Test]
  public function unrelatedOriginPreventsQualificationWithoutSavingOrEmittingEvent(): void
  {
    $request = $this->request();
    $requests = $this->createMock(ServiceRequestRepositoryPort::class);
    $requests->expects(self::once())->method('find')->with(self::REQUEST, self::ORGANIZATION, true)->willReturn($request);
    $requests->expects(self::never())->method('save');
    $equipment = $this->createStub(ServiceRequestEquipmentTargetPort::class);
    $equipment->method('find')->willReturn(new ServiceRequestEquipmentTarget(self::EQUIPMENT, 'Extincteur', 'EXT-001', 'active', self::SITE));
    $sites = $this->createStub(ServiceRequestSiteTargetPort::class);
    $sites->method('find')->willReturn(new ServiceRequestSiteTarget(self::SITE, 'Site', false, null));
    $origins = $this->createMock(ServiceRequestOriginPort::class);
    $origins->expects(self::once())->method('assertMatches')->with(self::ORGANIZATION, self::EQUIPMENT, self::SITE, self::INSPECTION, null)->willThrowException(new ServiceRequestOriginUnavailable());
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');

    $this->expectException(ServiceRequestOriginUnavailable::class);
    $this->handler($requests, $origins, $equipment, $sites, $events)(new ChangeServiceRequestCommand(self::ACTOR, self::ORGANIZATION, self::REQUEST, 'qualify', 1));
  }

  #[Test]
  public function rejectionRemainsPossibleWithoutReadingTheCurrentTarget(): void
  {
    $request = $this->request();
    $requests = $this->createMock(ServiceRequestRepositoryPort::class);
    $requests->expects(self::once())->method('find')->with(self::REQUEST, self::ORGANIZATION, true)->willReturn($request);
    $requests->expects(self::once())->method('save')->with(self::callback(static function (ServiceRequest $saved): bool {
      self::assertSame('rejected', $saved->status);
      self::assertSame('Demande déjà traitée.', $saved->decisionReason);
      self::assertSame(2, $saved->revision);

      return true;
    }), 1);
    $equipment = $this->createMock(ServiceRequestEquipmentTargetPort::class);
    $equipment->expects(self::never())->method('find');
    $sites = $this->createMock(ServiceRequestSiteTargetPort::class);
    $sites->expects(self::never())->method('lock');
    $sites->expects(self::never())->method('find');
    $origins = $this->createMock(ServiceRequestOriginPort::class);
    $origins->expects(self::never())->method('assertMatches');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $event): bool => $event instanceof ServiceRequestChangedEvent && 'reject' === $event->change && 2 === $event->revision));

    $result = $this->handler($requests, $origins, $equipment, $sites, $events)(new ChangeServiceRequestCommand(self::ACTOR, self::ORGANIZATION, self::REQUEST, 'reject', 1, reason: ' Demande déjà traitée. '));

    self::assertSame('rejected', $result->request->status);
    self::assertSame($request->targetSnapshot, $result->request->targetSnapshot);
  }

  #[Test]
  public function emptyPatchDoesNotSaveOrEmitAnotherWorkflowFact(): void
  {
    $request = $this->request();
    $requests = $this->createMock(ServiceRequestRepositoryPort::class);
    $requests->expects(self::once())->method('find')->with(self::REQUEST, self::ORGANIZATION, true)->willReturn($request);
    $requests->expects(self::never())->method('save');
    $equipment = $this->createStub(ServiceRequestEquipmentTargetPort::class);
    $sites = $this->createStub(ServiceRequestSiteTargetPort::class);
    $origins = $this->createMock(ServiceRequestOriginPort::class);
    $origins->expects(self::never())->method('assertMatches');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');

    $result = $this->handler($requests, $origins, $equipment, $sites, $events)(new ChangeServiceRequestCommand(self::ACTOR, self::ORGANIZATION, self::REQUEST, 'patch', 1));

    self::assertSame(1, $result->request->revision);
    self::assertSame($request->updatedAt, $result->request->updatedAt);
  }

  #[Test]
  public function qualificationCannotReplaceAnAlreadySelectedEquipment(): void
  {
    $requests = $this->createMock(ServiceRequestRepositoryPort::class);
    $requests->expects(self::once())->method('find')->with(self::REQUEST, self::ORGANIZATION, true)->willReturn($this->request());
    $requests->expects(self::never())->method('save');
    $equipment = $this->createMock(ServiceRequestEquipmentTargetPort::class);
    $equipment->expects(self::never())->method('find');
    $sites = $this->createMock(ServiceRequestSiteTargetPort::class);
    $sites->expects(self::never())->method('lock');
    $sites->expects(self::never())->method('find');
    $origins = $this->createMock(ServiceRequestOriginPort::class);
    $origins->expects(self::never())->method('assertMatches');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');

    try {
      $this->handler($requests, $origins, $equipment, $sites, $events)(new ChangeServiceRequestCommand(self::ACTOR, self::ORGANIZATION, self::REQUEST, 'qualify', 1, equipmentId: self::OTHER_EQUIPMENT));
      self::fail('Expected a target replacement conflict.');
    } catch (ServiceRequestException $exception) {
      self::assertSame('service_request_transition_conflict', $exception->reason);
    }
    self::assertFalse($this->inTransaction);
  }

  private function handler(ServiceRequestRepositoryPort $requests, ServiceRequestOriginPort $origins, ServiceRequestEquipmentTargetPort $equipment, ServiceRequestSiteTargetPort $sites, EventDispatcherPort $events): ChangeServiceRequestHandler
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with(self::ACTOR, self::ORGANIZATION, 'organization.service_requests.manage')->willReturn(OrganizationAccessDecision::GRANTED);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-06T12:00:00+00:00'));
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(function (callable $work): mixed {
      self::assertFalse($this->inTransaction);
      $this->inTransaction = true;

      try {
        return $work();
      } finally {
        $this->inTransaction = false;
      }
    });

    return new ChangeServiceRequestHandler($requests, new ServiceRequestAccessGuard($authorization), new ServiceRequestTargetGuard($equipment, $sites), $origins, $clock, $transactions, $events);
  }

  private function request(?string $equipmentId = self::EQUIPMENT): ServiceRequest
  {
    return ServiceRequest::create(self::REQUEST, self::ORGANIZATION, new ServiceRequestTarget($equipmentId, self::SITE, [
      'equipment' => null === $equipmentId ? null : ['id' => $equipmentId, 'name' => 'Extincteur', 'assetCode' => 'EXT-001', 'status' => 'active'],
      'site' => ['id' => self::SITE, 'name' => 'Reported site'],
      'customer' => ['id' => self::CUSTOMER, 'name' => 'Reported customer'],
    ], self::INSPECTION, null), new ServiceRequestContent('Poignée cassée', 'Le contrôle signale une poignée cassée.', 'normal'), new DateTimeImmutable('2026-10-06T11:00:00+00:00'));
  }
}
