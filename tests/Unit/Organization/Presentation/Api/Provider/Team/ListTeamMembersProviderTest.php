<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Team;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Team\ListTeamMembers\{
  ListTeamMembersQuery,
  ListTeamMembersResult,
  TeamMembershipResult
};
use Organization\Domain\Exception\TeamNotFoundException;
use Organization\Presentation\Api\Provider\Team\ListTeamMembersProvider;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test ListTeamMembersProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListTeamMembersProvider::class)]
final class ListTeamMembersProviderTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440030';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440011';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'missing teamId' => [['organizationId' => self::ORGANIZATION_ID]];
    yield 'blank organizationId' => [['organizationId' => '', 'teamId' => self::TEAM_ID]];
  }

  #[Test]
  public function testProvideMapsEveryMembershipRow(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListTeamMembersQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
        && self::TEAM_ID === $query->teamId))
      ->willReturn(new ListTeamMembersResult([
        new TeamMembershipResult(
          memberId: self::MEMBER_ID,
          role: 'lead',
          addedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        ),
        new TeamMembershipResult(
          memberId: '550e8400-e29b-41d4-a716-446655440012',
          role: null,
          addedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
        ),
      ]));

    $outputs = $this->createProvider($queryBus)->provide(new GetCollection(), $this->uriVariables());

    self::assertCount(2, $outputs);
    self::assertSame(self::MEMBER_ID, $outputs[0]->memberId);
    self::assertSame('lead', $outputs[0]->role);
    self::assertSame('2026-01-01T00:00:00+00:00', $outputs[0]->addedAt);
    self::assertNull($outputs[1]->role);
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProvideReturnsAnEmptyListWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    self::assertSame([], $this->createProvider()->provide(new GetCollection(), $uriVariables));
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListTeamMembersProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->authorization(true),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), $this->uriVariables());
  }

  #[Test]
  public function testProvideThrowsWhenThePermissionIsMissing(): void
  {
    $provider = new ListTeamMembersProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->authorization(false),
      security: $this->securityWithUser(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.teams.read permission.');

    $provider->provide(new GetCollection(), $this->uriVariables());
  }

  #[Test]
  public function testProvideMapsAMissingTeamToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(TeamNotFoundException::withId(self::TEAM_ID));

    $this->expectException(TeamNotFoundException::class);

    $this->createProvider($queryBus)->provide(new GetCollection(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'teamId' => self::TEAM_ID];
  }

  private function createProvider(?QueryBusPort $queryBus = null): ListTeamMembersProvider
  {
    return new ListTeamMembersProvider(
      queryBus: $queryBus ?? $this->createStub(QueryBusPort::class),
      authorization: $this->authorization(true),
      security: $this->securityWithUser(),
    );
  }

  private function authorization(bool $granted): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($granted);

    return $authorization;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
  // #endregion
}
