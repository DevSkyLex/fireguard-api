<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentKpis\{GetEquipmentKpisQuery, GetEquipmentKpisResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentKpiOutput;
use Equipment\Presentation\Api\Provider\Equipment\GetEquipmentKpisProvider;
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

#[CoversClass(GetEquipmentKpisProvider::class)]
final class GetEquipmentKpisProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsAccessDeniedWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new GetEquipmentKpisProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655442001']);
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenOrganizationIdMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442010');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    $provider = new GetEquipmentKpisProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(operation: new Get(), uriVariables: []);
  }

  #[Test]
  public function testProvideThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442020';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442021');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $provider = new GetEquipmentKpisProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenOrganizationIsOutsideCallerScope(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442022';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442023');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $provider = new GetEquipmentKpisProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideMapsWrappedInvalidArgumentToHttp400(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442030';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442031');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new GetEquipmentKpisQuery(organizationId: $organizationId)),
      exceptions: [new InvalidArgumentException('Invalid organizationId.')],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetEquipmentKpisProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideMapsResultToOutput(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442040';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442041');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::equalTo(new GetEquipmentKpisQuery(organizationId: $organizationId)))
      ->willReturn(new GetEquipmentKpisResult(
        totalAssets: 42,
        compliant: 30,
        dueSoon: 5,
        openNonConformities: 3,
      ));

    $provider = new GetEquipmentKpisProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(operation: new Get(), uriVariables: ['organizationId' => $organizationId]);

    self::assertInstanceOf(EquipmentKpiOutput::class, $output);
    self::assertSame(42, $output->totalAssets);
    self::assertSame(30, $output->compliant);
    self::assertSame(5, $output->dueSoon);
    self::assertSame(3, $output->openNonConformities);
  }

  #[Test]
  public function testProvideMapsADirectInvalidArgumentToHttp400(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442050';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442051');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InvalidArgumentException('Invalid organizationId.'));

    $provider = new GetEquipmentKpisProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid organizationId.');

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => $organizationId]);
  }

  #[Test]
  public function testProvideRethrowsAWrappedFailureThatIsNotAnInvalidArgument(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442060';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655442061');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new GetEquipmentKpisQuery(organizationId: $organizationId)),
      exceptions: [new RuntimeException('database is down')],
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetEquipmentKpisProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    // An infrastructure failure must surface as a 500, never be masked as a 400.
    $this->expectException(MessengerRuntimeException::class);

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => $organizationId]);
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
