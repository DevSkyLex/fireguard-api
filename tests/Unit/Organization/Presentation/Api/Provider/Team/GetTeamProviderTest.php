<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Team;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Team\GetTeam\{GetTeamQuery, GetTeamResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Presentation\Api\Provider\Team\GetTeamProvider;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test GetTeamProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetTeamProvider::class)]
final class GetTeamProviderTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440030';

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
    yield 'blank teamId' => [['organizationId' => self::ORGANIZATION_ID, 'teamId' => '']];
  }

  #[Test]
  public function testProvideReturnsTheTeamOutput(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetTeamQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
        && self::TEAM_ID === $query->teamId))
      ->willReturn($this->teamResult());

    $output = $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());

    self::assertNotNull($output);
    self::assertSame(self::TEAM_ID, $output->id);
    self::assertSame('Night shift', $output->name);
    self::assertSame(4, $output->memberCount);
    self::assertSame('2026-01-02T00:00:00+00:00', $output->updatedAt);
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProvideReturnsNullWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    self::assertNull($this->createProvider()->provide(new Get(), $uriVariables));
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetTeamProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->authorization(true),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), $this->uriVariables());
  }

  #[Test]
  public function testProvideThrowsWhenThePermissionIsMissing(): void
  {
    $provider = new GetTeamProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->authorization(false),
      security: $this->securityWithUser(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.teams.read permission.');

    $provider->provide(new Get(), $this->uriVariables());
  }

  #[Test]
  public function testProvideMapsAMissingTeamToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(TeamNotFoundException::withId(self::TEAM_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());
  }

  #[Test]
  public function testProvideMapsAMissingOrganizationToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(OrganizationNotFoundException::withId(self::ORGANIZATION_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'teamId' => self::TEAM_ID];
  }

  private function createProvider(?QueryBusPort $queryBus = null): GetTeamProvider
  {
    return new GetTeamProvider(
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

  private function teamResult(): GetTeamResult
  {
    return new GetTeamResult(
      id: self::TEAM_ID,
      organizationId: self::ORGANIZATION_ID,
      name: 'Night shift',
      description: 'Covers 22:00-06:00',
      memberCount: 4,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }
  // #endregion
}
