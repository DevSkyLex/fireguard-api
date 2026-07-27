<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant\{
  RemoveChannelParticipantCommand,
  RemoveChannelParticipantResult
};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Processor\Channel\RemoveChannelParticipantProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};

/**
 * Test RemoveChannelParticipantProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RemoveChannelParticipantProcessor::class)]
final class RemoveChannelParticipantProcessorTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441200';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'missing memberId' => [['id' => self::CHANNEL_ID]];
    yield 'missing id' => [['memberId' => self::MEMBER_ID]];
  }

  #[Test]
  public function testProcessDispatchesTheRemoveCommandAndReturnsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RemoveChannelParticipantCommand $command): bool => self::USER_ID === $command->userId
        && self::CHANNEL_ID === $command->conversationId
        && self::MEMBER_ID === $command->memberId))
      ->willReturn(new RemoveChannelParticipantResult(self::CHANNEL_ID, self::MEMBER_ID));

    $this->createProcessor($commandBus)->process(
      null,
      new Delete(),
      ['id' => self::CHANNEL_ID, 'memberId' => self::MEMBER_ID],
    );
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(null, new Delete(), $uriVariables);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RemoveChannelParticipantProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::CHANNEL_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::conversation(self::CHANNEL_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(
      null,
      new Delete(),
      ['id' => self::CHANNEL_ID, 'memberId' => self::MEMBER_ID],
    );
  }

  private function createProcessor(CommandBusPort $commandBus): RemoveChannelParticipantProcessor
  {
    return new RemoveChannelParticipantProcessor($commandBus, $this->securityWithUser());
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
