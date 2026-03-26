<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationInspectionStatistics\{GetOrganizationInspectionStatisticsQuery, GetOrganizationInspectionStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInspectionStatisticsOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationInspectionStatisticsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(GetOrganizationInspectionStatisticsProvider::class)]
final class GetOrganizationInspectionStatisticsProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsNullWhenOrganizationIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442000'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationInspectionStatisticsProvider(
      queryBus: $queryBus,
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    self::assertNull($provider->provide(new Get(), []));
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442000'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655442000', '550e8400-e29b-41d4-a716-446655442010', 'organization.inspection.read')
      ->willReturn(false);

    $provider = new GetOrganizationInspectionStatisticsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655442010']);
  }

  #[Test]
  public function testProvideMapsDetailedInspectionStatistics(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442000'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationInspectionStatisticsQuery::class))
      ->willReturn(new GetOrganizationInspectionStatisticsResult(
        totalCount: 21,
        draftCount: 3,
        submittedCount: 7,
        closedCount: 11,
        passCount: 12,
        failCount: 6,
        partialCount: 3,
        countsByInspectorType: [
          'user' => 16,
          'external' => 5,
        ],
        performedLast7DaysCount: 4,
        performedLast30DaysCount: 13,
      ));

    $provider = new GetOrganizationInspectionStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655442010']);

    self::assertInstanceOf(OrganizationInspectionStatisticsOutput::class, $output);
    self::assertSame(21, $output->totalCount);
    self::assertSame(3, $output->draftCount);
    self::assertSame(7, $output->submittedCount);
    self::assertSame(11, $output->closedCount);
    self::assertSame(12, $output->passCount);
    self::assertSame(6, $output->failCount);
    self::assertSame(3, $output->partialCount);
    self::assertSame(16, $output->countsByInspectorType['user']);
    self::assertSame(5, $output->countsByInspectorType['external']);
    self::assertSame(4, $output->performedLast7DaysCount);
    self::assertSame(13, $output->performedLast30DaysCount);
  }

  #[Test]
  public function testProvideMapsOrganizationNotFoundToHttp404(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442000'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationInspectionStatisticsQuery::class))
      ->willThrowException(OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655442010'));

    $provider = new GetOrganizationInspectionStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655442010']);
  }

  #[Test]
  public function testProvideMapsWrappedPermissionDenialToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442000'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationInspectionStatisticsQuery('550e8400-e29b-41d4-a716-446655442010', '550e8400-e29b-41d4-a716-446655442000')),
      [OrganizationAccessDeniedException::missingPermission('organization.inspection.read')],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationInspectionStatisticsQuery::class))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationInspectionStatisticsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655442010']);
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
