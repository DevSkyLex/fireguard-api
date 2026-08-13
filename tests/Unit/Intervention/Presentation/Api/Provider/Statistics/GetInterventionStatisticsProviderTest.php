<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider\Statistics;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Statistics\{InterventionStatisticsResponsibleEntry, InterventionStatisticsSiteEntry};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics\{GetInterventionStatisticsQuery, GetInterventionStatisticsResult};
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Intervention\Presentation\Api\Dto\Output\Statistics\InterventionStatisticsOutput;
use Intervention\Presentation\Api\Provider\Statistics\GetInterventionStatisticsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

#[CoversClass(GetInterventionStatisticsProvider::class)]
final class GetInterventionStatisticsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449201';

  #[Test]
  public function testProvideThrowsAccessDeniedWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new GetInterventionStatisticsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
      requestStack: new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(operation: new Get());
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenOrganizationMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655449202');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/interventions/statistics'));

    $provider = new GetInterventionStatisticsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(operation: new Get());
  }

  #[Test]
  public function testProvideMapsAccessDeniedFromTheHandlerToHttp403(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655449203');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/interventions/statistics?organization=/api/organizations/' . self::ORG_ID));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InterventionAccessDeniedException('Missing organization.interventions.read permission.'));

    $provider = new GetInterventionStatisticsProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(operation: new Get());
  }

  #[Test]
  public function testProvideMapsResultToOutput(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655449204');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/interventions/statistics?organization=/api/organizations/' . self::ORG_ID));

    $result = new GetInterventionStatisticsResult(
      total: 10,
      byStatus: ['draft' => 2, 'planned' => 8],
      byPriority: ['normal' => 10],
      overdue: 1,
      dueSoon: 2,
      bySite: [new InterventionStatisticsSiteEntry('site-1', 'Main Warehouse', 5)],
      byResponsible: [new InterventionStatisticsResponsibleEntry('member-1', 'Jane Doe', 3)],
      averagePublicationDays: 4.25,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::equalTo(new GetInterventionStatisticsQuery($user->getId(), self::ORG_ID)))
      ->willReturn($result);

    $provider = new GetInterventionStatisticsProvider(
      queryBus: $queryBus,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $provider->provide(operation: new Get());

    self::assertInstanceOf(InterventionStatisticsOutput::class, $output);
    self::assertSame(10, $output->total);
    self::assertSame(['draft' => 2, 'planned' => 8], $output->byStatus);
    self::assertSame(['normal' => 10], $output->byPriority);
    self::assertSame(1, $output->overdue);
    self::assertSame(2, $output->dueSoon);
    self::assertCount(1, $output->bySite);
    self::assertSame('site-1', $output->bySite[0]->siteId);
    self::assertSame('Main Warehouse', $output->bySite[0]->siteName);
    self::assertSame(5, $output->bySite[0]->count);
    self::assertCount(1, $output->byResponsible);
    self::assertSame('member-1', $output->byResponsible[0]->memberId);
    self::assertSame('Jane Doe', $output->byResponsible[0]->displayName);
    self::assertSame(3, $output->byResponsible[0]->count);
    self::assertSame(4.25, $output->averagePublicationDays);
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
