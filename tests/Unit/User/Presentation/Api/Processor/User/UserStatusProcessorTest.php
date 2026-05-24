<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Post;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\User\ActivateUser\ActivateUserCommand;
use User\Application\UseCase\Command\User\DeactivateUser\DeactivateUserCommand;
use User\Application\UseCase\Command\User\VerifyUserEmail\VerifyUserEmailCommand;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};
use User\Presentation\Api\Dto\Output\User\UserOutput;
use User\Presentation\Api\Processor\User\{ActivateUserProcessor, DeactivateUserProcessor, VerifyUserEmailProcessor};

/**
 * Test UserStatusProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ActivateUserProcessor::class)]
#[CoversClass(DeactivateUserProcessor::class)]
#[CoversClass(VerifyUserEmailProcessor::class)]
final class UserStatusProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testActivateReturnsNullWhenIdMissing(): void
  {
    $processor = new ActivateUserProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
    );

    $result = $processor->process(null, new Post(), ['id' => null]);

    self::assertNull($result);
  }

  #[Test]
  public function testActivateMapsOutput(): void
  {
    $userView = $this->createUserView();

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (ActivateUserCommand $command) => 'user-1' === $command->id,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    $processor = new ActivateUserProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $output = $processor->process(null, new Post(), ['id' => 'user-1']);

    self::assertInstanceOf(UserOutput::class, $output);
    self::assertSame('user-1', $output->id);
    self::assertSame('jdoe', $output->username);
  }

  #[Test]
  public function testActivateReturnsNullWhenUserMissing(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(ActivateUserCommand::class));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: null));

    $processor = new ActivateUserProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    self::assertNull($processor->process(null, new Post(), ['id' => 'user-1']));
  }

  #[Test]
  public function testDeactivateReturnsNullWhenIdMissing(): void
  {
    $processor = new DeactivateUserProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
    );

    $result = $processor->process(null, new Post(), ['id' => null]);

    self::assertNull($result);
  }

  #[Test]
  public function testDeactivateMapsOutput(): void
  {
    $userView = $this->createUserView();

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (DeactivateUserCommand $command) => 'user-1' === $command->id,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    $processor = new DeactivateUserProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $output = $processor->process(null, new Post(), ['id' => 'user-1']);

    self::assertInstanceOf(UserOutput::class, $output);
    self::assertSame('user-1', $output->id);
    self::assertSame('jdoe', $output->username);
  }

  #[Test]
  public function testDeactivateReturnsNullWhenUserMissing(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(DeactivateUserCommand::class));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: null));

    $processor = new DeactivateUserProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    self::assertNull($processor->process(null, new Post(), ['id' => 'user-1']));
  }

  #[Test]
  public function testVerifyEmailReturnsNullWhenIdMissing(): void
  {
    $processor = new VerifyUserEmailProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
    );

    $result = $processor->process(null, new Post(), ['id' => null]);

    self::assertNull($result);
  }

  #[Test]
  public function testVerifyEmailMapsOutput(): void
  {
    $userView = $this->createUserView();

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (VerifyUserEmailCommand $command) => 'user-1' === $command->id,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    $processor = new VerifyUserEmailProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $output = $processor->process(null, new Post(), ['id' => 'user-1']);

    self::assertInstanceOf(UserOutput::class, $output);
    self::assertSame('user-1', $output->id);
    self::assertSame('jdoe', $output->username);
  }

  #[Test]
  public function testVerifyEmailReturnsNullWhenUserMissing(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(VerifyUserEmailCommand::class));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetUserResult(user: null));

    $processor = new VerifyUserEmailProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    self::assertNull($processor->process(null, new Post(), ['id' => 'user-1']));
  }

  private function createUserView(): UserView
  {
    return new UserView(
      id: 'user-1',
      username: 'jdoe',
      email: 'jdoe@example.com',
      firstName: 'John',
      lastName: 'Doe',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      lastLoginAt: null,
      canLogin: true,
    );
  }
  // #endregion
}
