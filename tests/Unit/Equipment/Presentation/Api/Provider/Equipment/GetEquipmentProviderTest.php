<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Output\Equipment\{EquipmentOutput, TagOutput};
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
use Equipment\Presentation\Api\Provider\Equipment\GetEquipmentProvider;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(GetEquipmentProvider::class)]
final class GetEquipmentProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655441401';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441402';

  #[Test]
  public function testProvideMapsWrappedNotFoundToHttp404(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441300';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441301';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441302');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new GetEquipmentQuery(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
      )),
      exceptions: [EquipmentNotFoundException::withId($equipmentId)],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetEquipmentProvider(
      outputFactory: new EquipmentOutputFactory(),
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );
  }

  #[Test]
  public function testProvideMapsResultToOutput(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441310';
    $equipmentId = '550e8400-e29b-41d4-a716-446655441311';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441312');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $now = new DateTimeImmutable('2026-03-02T10:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetEquipmentQuery::class))
      ->willReturn(new GetEquipmentResult(
        equipmentId: $equipmentId,
        organizationId: $organizationId,
        facilityId: null,
        type: 'fire_extinguisher',
        subType: 'CO2 6kg',
        brand: 'Sicli',
        model: 'Pro 6',
        serialNumber: 'EXT-2026-001',
        locationLabel: 'Building A - Floor 1',
        status: 'operational',
        installedAt: '2026-01-01T00:00:00+00:00',
        commissionedAt: '2026-01-02T00:00:00+00:00',
        tags: [['id' => '550e8400-e29b-41d4-a716-446655441400', 'name' => 'urgent', 'organizationId' => $organizationId]],
        createdAt: $now,
        updatedAt: $now,
        maintenanceDueStatus: 'due_soon',
      ));

    $provider = new GetEquipmentProvider(
      outputFactory: new EquipmentOutputFactory(),
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => $organizationId,
        'equipmentId' => $equipmentId,
      ],
    );

    self::assertInstanceOf(EquipmentOutput::class, $output);
    self::assertSame($equipmentId, $output->id);
    self::assertSame('fire_extinguisher', $output->type);
    self::assertSame('Sicli', $output->brand);
    self::assertSame('EXT-2026-001', $output->serialNumber);
    self::assertSame('operational', $output->status);
    self::assertCount(1, $output->tags);
    self::assertInstanceOf(TagOutput::class, $output->tags[0]);
    self::assertSame('urgent', $output->tags[0]->name);
    self::assertSame($organizationId, $output->tags[0]->organizationId);
    self::assertSame('due_soon', $output->maintenanceDueStatus);
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetEquipmentProvider(
      outputFactory: new EquipmentOutputFactory(),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenUriVariablesAreMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser(self::USER_ID));

    $provider = new GetEquipmentProvider(
      outputFactory: new EquipmentOutputFactory(),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWithoutTheReadPermission(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser(self::USER_ID));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $provider = new GetEquipmentProvider(
      outputFactory: new EquipmentOutputFactory(),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenOrganizationIsOutsideCallerScope(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser(self::USER_ID));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $provider = new GetEquipmentProvider(
      outputFactory: new EquipmentOutputFactory(),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsADirectEquipmentNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->providerThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->providerThrowing(new InvalidArgumentException('Invalid equipment identifier.'))->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetEquipmentQuery(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID)),
      [new InvalidArgumentException('Invalid equipment identifier.')],
    );

    $this->expectException(BadRequestHttpException::class);

    $this->providerThrowing(MessengerRuntimeException::wrap($handlerFailure))->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProvideRethrowsAMessengerFailureItCannotTranslate(): void
  {
    // A handler failure whose cause is neither a not-found nor an invalid
    // argument must surface as-is rather than be masked behind a 4xx.
    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetEquipmentQuery(organizationId: self::ORG_ID, equipmentId: self::EQUIP_ID)),
      [new RuntimeException('Equipment read model is unavailable.')],
    );

    $this->expectException(MessengerRuntimeException::class);

    $this->providerThrowing(MessengerRuntimeException::wrap($handlerFailure))->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  private function providerThrowing(Throwable $exception): GetEquipmentProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser(self::USER_ID));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($exception);

    return new GetEquipmentProvider(
      outputFactory: new EquipmentOutputFactory(),
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
