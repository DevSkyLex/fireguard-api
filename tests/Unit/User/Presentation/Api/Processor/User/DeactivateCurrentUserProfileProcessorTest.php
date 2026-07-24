<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\User\DeactivateUser\DeactivateUserCommand;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\{
  GetCurrentUserProfileQuery,
  GetCurrentUserProfileResult
};
use User\Domain\Exception\UserNotFoundException;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;
use User\Presentation\Api\Processor\User\DeactivateCurrentUserProfileProcessor;

/**
 * Test DeactivateCurrentUserProfileProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeactivateCurrentUserProfileProcessor::class)]
final class DeactivateCurrentUserProfileProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new DeactivateCurrentUserProfileProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessDeactivatesCurrentUserAndReturnsProfile(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442201';
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (DeactivateUserCommand $command): bool => $userId === $command->id,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(
        static fn (GetCurrentUserProfileQuery $query): bool => $userId === $query->userId,
      ))
      ->willReturn(new GetCurrentUserProfileResult(
        user: new UserView(
          id: $userId,
          username: 'jdoe',
          email: 'jdoe@example.com',
          firstName: 'John',
          lastName: 'Doe',
          avatarUrl: null,
          status: 'inactive',
          emailVerified: true,
          tenantId: null,
          createdAt: new DateTimeImmutable('2026-02-01T10:00:00+00:00'),
          lastLoginAt: null,
          canLogin: false,
        ),
        roles: ['user'],
        permissions: ['profile.read', 'profile.update'],
        totpEnabled: false,
      ));

    $processor = new DeactivateCurrentUserProfileProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      security: $security,
    );

    $output = $processor->process(null, new Post());

    self::assertInstanceOf(CurrentUserProfileOutput::class, $output);
    self::assertSame($userId, $output->id);
    self::assertSame('inactive', $output->status);
  }

  #[Test]
  public function testProcessMapsMissingAuthenticatedUserToNotFound(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442202';
    $security = $this->createStub(Security::class);
    $security->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(UserNotFoundException::withId($userId));

    $processor = new DeactivateCurrentUserProfileProcessor(
      commandBus: $commandBus,
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post());
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
  // #endregion
}
