<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs\{ListMaintenanceLogsQuery, ListMaintenanceLogsResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Output\Equipment\MaintenanceLogOutput;
use Equipment\Presentation\Api\Provider\Equipment\ListMaintenanceLogsProvider;
use InvalidArgumentException;
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function iterator_to_array;

#[CoversClass(ListMaintenanceLogsProvider::class)]
final class ListMaintenanceLogsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655470001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655470002';

  #[Test]
  public function testProvideThrowsAccessDeniedWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListMaintenanceLogsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenUriVariablesMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655470010');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $provider = new ListMaintenanceLogsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655470011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.read')
      ->willReturn(false);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListMaintenanceLogsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsWrappedEquipmentNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655470012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new ListMaintenanceLogsQuery(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        pagination: new Pagination(offset: 0, limit: 20),
      )),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new ListMaintenanceLogsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideReturnsEmptyPaginatorWhenNoLogs(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655470013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListMaintenanceLogsQuery::class))
      ->willReturn(new ListMaintenanceLogsResult(logs: [], total: 0));

    $provider = new ListMaintenanceLogsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $paginator = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
    self::assertSame(0.0, $paginator->getTotalItems());
  }

  #[Test]
  public function testProvideMapsResultToMaintenanceLogOutputPaginator(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655470014');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $logId = '550e8400-e29b-41d4-a716-446655470099';
    $startedAt = '2026-03-25T08:00:00+00:00';

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (ListMaintenanceLogsQuery $q): bool {
        return self::ORG_ID === $q->organizationId && self::EQUIP_ID === $q->equipmentId;
      }))
      ->willReturn(new ListMaintenanceLogsResult(
        logs: [[
          'id' => $logId,
          'equipmentId' => self::EQUIP_ID,
          'organizationId' => self::ORG_ID,
          'startedAt' => $startedAt,
          'completedAt' => null,
          'source' => 'intervention',
          'interventionId' => '550e8400-e29b-41d4-a716-446655470100',
          'interventionNumber' => 12,
          'workItemAction' => 'status_change',
          'actorId' => '550e8400-e29b-41d4-a716-446655470101',
          'summary' => 'Replaced detector',
        ]],
        total: 1,
      ));

    $provider = new ListMaintenanceLogsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $paginator = $provider->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(TraversablePaginator::class, $paginator);
    self::assertSame(1.0, $paginator->getTotalItems());

    $items = iterator_to_array($paginator);
    self::assertCount(1, $items);
    self::assertInstanceOf(MaintenanceLogOutput::class, $items[0]);
    self::assertSame($logId, $items[0]->id);
    self::assertSame(self::EQUIP_ID, $items[0]->equipmentId);
    self::assertSame(self::ORG_ID, $items[0]->organizationId);
    self::assertSame($startedAt, $items[0]->startedAt);
    self::assertNull($items[0]->completedAt);
    self::assertSame('intervention', $items[0]->source);
    self::assertSame('550e8400-e29b-41d4-a716-446655470100', $items[0]->interventionId);
    self::assertSame(12, $items[0]->interventionNumber);
    self::assertSame('status_change', $items[0]->workItemAction);
    self::assertSame('550e8400-e29b-41d4-a716-446655470101', $items[0]->actorId);
    self::assertSame('Replaced detector', $items[0]->summary);
  }

  #[Test]
  public function testProvideMapsADirectEquipmentNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->providerThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->providerThrowing(new InvalidArgumentException('Invalid equipment identifier.'))->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->providerThrowing(
      MessengerRuntimeException::wrap($this->handlerFailure(new InvalidArgumentException('Invalid equipment identifier.'))),
    )->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideRethrowsAMessengerFailureItCannotClassify(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->providerThrowing(
      MessengerRuntimeException::wrap($this->handlerFailure(new LogicException('Unexpected handler failure.'))),
    )->provide(
      operation: new GetCollection(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  private function handlerFailure(Throwable $wrapped): HandlerFailedException
  {
    return new HandlerFailedException(
      new Envelope(new ListMaintenanceLogsQuery(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        pagination: new Pagination(offset: 0, limit: 20),
      )),
      [$wrapped],
    );
  }

  private function providerThrowing(Throwable $exception): ListMaintenanceLogsProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655470012'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($exception);

    return new ListMaintenanceLogsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );
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
