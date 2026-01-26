<?php

declare(strict_types=1);

namespace Tests\Unit\User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Put;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\User\UpdateUser\UpdateUserCommand;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};
use User\Presentation\Api\Dto\Input\User\UserInput;
use User\Presentation\Api\Dto\Output\User\UserOutput;
use User\Presentation\Api\Processor\User\UpdateUserProcessor;

/**
 * Test UpdateUserProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateUserProcessor::class)]
final class UpdateUserProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessReturnsNullWhenDataInvalid(): void
  {
    $processor = new UpdateUserProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $result = $processor->process('invalid', new Put(), ['id' => 'user-1']);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessReturnsNullWhenIdMissing(): void
  {
    $processor = new UpdateUserProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $input = new UserInput();
    $result = $processor->process($input, new Put(), ['id' => null]);

    self::assertNull($result);
  }

  #[Test]
  public function testProcessMapsOutput(): void
  {
    $userView = new UserView(
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

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        fn (UpdateUserCommand $command) => 'user-1' === $command->id
          && 'Jane' === $command->firstName
          && 'Doe' === $command->lastName
          && 'https://example.com/avatar.png' === $command->avatarUrl,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserQuery::class))
      ->willReturn(new GetUserResult(user: $userView));

    $processor = new UpdateUserProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $input = new UserInput();
    $input->firstName = 'Jane';
    $input->lastName = 'Doe';
    $input->avatarUrl = 'https://example.com/avatar.png';

    $output = $processor->process($input, new Put(), ['id' => 'user-1']);

    self::assertInstanceOf(UserOutput::class, $output);
    self::assertSame('jdoe', $output->username);
    self::assertSame('active', $output->status);
  }
  // #endregion
}
