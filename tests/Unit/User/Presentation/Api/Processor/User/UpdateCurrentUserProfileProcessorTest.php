<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\User\UpdateUser\UpdateUserCommand;
use User\Application\UseCase\Query\User\GetCurrentUserProfile\{
  GetCurrentUserProfileQuery,
  GetCurrentUserProfileResult
};
use User\Domain\Exception\UserNotFoundException;
use User\Presentation\Api\Dto\Input\User\CurrentUserProfileInput;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;
use User\Presentation\Api\Processor\User\UpdateCurrentUserProfileProcessor;

#[CoversClass(UpdateCurrentUserProfileProcessor::class)]
final class UpdateCurrentUserProfileProcessorTest extends TestCase
{
  #[Test]
  public function testProcessReturnsNullWhenDataInvalid(): void
  {
    $processor = new UpdateCurrentUserProfileProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      security: $this->createStub(Security::class),
    );

    self::assertNull($processor->process('invalid', new Patch()));
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new UpdateCurrentUserProfileProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new CurrentUserProfileInput(), new Patch());
  }

  #[Test]
  public function testProcessUpdatesAndMapsCurrentUserProfile(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442101';
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (UpdateUserCommand $command): bool => $userId === $command->id
          && 'Jane' === $command->firstName
          && 'Doe' === $command->lastName
          && null === $command->avatarUrl,
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
          firstName: 'Jane',
          lastName: 'Doe',
          avatarUrl: null,
          status: 'active',
          emailVerified: true,
          tenantId: null,
          createdAt: new DateTimeImmutable('2026-02-01T10:00:00+00:00'),
          lastLoginAt: null,
          canLogin: true,
        ),
        roles: ['user'],
        permissions: ['profile.read', 'profile.update'],
      ));

    $input = new CurrentUserProfileInput();
    $input->firstName = 'Jane';
    $input->lastName = 'Doe';

    $processor = new UpdateCurrentUserProfileProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
      security: $security,
    );

    $output = $processor->process($input, new Patch());

    self::assertInstanceOf(CurrentUserProfileOutput::class, $output);
    self::assertSame($userId, $output->id);
    self::assertSame('Jane', $output->firstName);
    self::assertSame(['profile.read', 'profile.update'], $output->permissions);
  }

  #[Test]
  public function testProcessMapsMissingAuthenticatedUserToNotFound(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655442102';
    $security = $this->createStub(Security::class);
    $security->method('getUser')
      ->willReturn($this->createSecurityUser($userId));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(UserNotFoundException::withId($userId));

    $processor = new UpdateCurrentUserProfileProcessor(
      commandBus: $commandBus,
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(UserNotFoundException::class);

    $processor->process(new CurrentUserProfileInput(), new Patch());
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
