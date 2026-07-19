<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Team;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Team\GetTeam\GetTeamResult;
use Organization\Application\UseCase\Query\Team\ListTeams\{ListTeamsQuery, ListTeamsResult};
use Organization\Presentation\Api\Dto\Output\Team\TeamOutput;
use Organization\Presentation\Api\Provider\Team\ListTeamsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListTeamsProvider::class)]
final class ListTeamsProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441800';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  #[Test]
  public function testProvideReturnsEmptyArrayWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new ListTeamsProvider(
      queryBus: $queryBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    self::assertSame([], $provider->provide(new GetCollection(), []));
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.teams.read')
      ->willReturn(false);

    $provider = new ListTeamsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideMapsTeamsResult(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-01T11:00:00+00:00');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListTeamsQuery::class))
      ->willReturn(new ListTeamsResult([
        new GetTeamResult(
          id: '550e8400-e29b-41d4-a716-446655441811',
          organizationId: self::ORGANIZATION_ID,
          name: 'Field crew A',
          description: 'Rooftop crew',
          memberCount: 3,
          createdAt: $createdAt,
          updatedAt: $createdAt,
        ),
      ]));

    $provider = new ListTeamsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertCount(1, $output);
    self::assertInstanceOf(TeamOutput::class, $output[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441811', $output[0]->id);
    self::assertSame(self::ORGANIZATION_ID, $output[0]->organizationId);
    self::assertSame('Field crew A', $output[0]->name);
    self::assertSame('Rooftop crew', $output[0]->description);
    self::assertSame(3, $output[0]->memberCount);
    self::assertSame($createdAt->format('c'), $output[0]->createdAt);
  }

  private function securityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
