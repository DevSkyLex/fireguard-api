<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentKpis\{GetEquipmentKpisQuery, GetEquipmentKpisResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentKpiOutput;
use Equipment\Presentation\Api\Provider\Equipment\GetEquipmentKpisProvider;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
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
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(false);

    $provider = new GetEquipmentKpisProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

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
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

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
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.equipment.read')
      ->willReturn(true);

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
