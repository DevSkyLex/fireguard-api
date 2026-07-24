<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\{
  GetCurrentUserProfileQuery,
  GetCurrentUserProfileResult
};
use User\Domain\Exception\UserNotFoundException;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;
use User\Presentation\Api\Provider\User\GetCurrentUserProfileProvider;

#[CoversClass(GetCurrentUserProfileProvider::class)]
final class GetCurrentUserProfileProviderTest extends TestCase
{
  #[Test]
  public function testProvideMapsCurrentUserProfile(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442101'));

    $user = new UserView(
      id: '550e8400-e29b-41d4-a716-446655442101',
      username: 'jdoe',
      email: 'jdoe@example.com',
      firstName: 'John',
      lastName: 'Doe',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2026-02-01T10:00:00+00:00'),
      lastLoginAt: new DateTimeImmutable('2026-02-02T10:00:00+00:00'),
      canLogin: true,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetCurrentUserProfileQuery $query): bool => '550e8400-e29b-41d4-a716-446655442101' === $query->userId))
      ->willReturn(new GetCurrentUserProfileResult(
        user: $user,
        roles: ['admin'],
        permissions: ['users.read', 'roles.read'],
        totpEnabled: true,
      ));

    $provider = new GetCurrentUserProfileProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get());

    self::assertInstanceOf(CurrentUserProfileOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655442101', $output->id);
    self::assertSame('jdoe', $output->username);
    self::assertSame(['admin'], $output->roles);
    self::assertSame(['users.read', 'roles.read'], $output->permissions);
    self::assertTrue($output->totpEnabled);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new GetCurrentUserProfileProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideMapsMissingAuthenticatedUserToNotFound(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442102'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(UserNotFoundException::withId('550e8400-e29b-41d4-a716-446655442102'));

    $provider = new GetCurrentUserProfileProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get());
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
