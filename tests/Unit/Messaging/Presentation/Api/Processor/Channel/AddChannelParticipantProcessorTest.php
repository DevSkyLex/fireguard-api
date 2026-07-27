<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ParticipantView;
use Messaging\Application\UseCase\Command\Channel\AddChannelParticipant\{
  AddChannelParticipantCommand,
  AddChannelParticipantResult
};
use Messaging\Domain\Exception\MessagingConflictException;
use Messaging\Presentation\Api\Dto\Input\Channel\AddChannelParticipantInput;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Processor\Channel\AddChannelParticipantProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException
};

/**
 * Test AddChannelParticipantProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddChannelParticipantProcessor::class)]
final class AddChannelParticipantProcessorTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441200';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesTheAddCommandAndReturnsTheParticipant(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (AddChannelParticipantCommand $command): bool => self::USER_ID === $command->userId
        && self::CHANNEL_ID === $command->conversationId
        && self::MEMBER_ID === $command->memberId
        && 'moderator' === $command->role))
      ->willReturn(new AddChannelParticipantResult($this->participantView()));

    $input = new AddChannelParticipantInput();
    $input->memberId = self::MEMBER_ID;
    $input->role = 'moderator';

    $output = $this->createProcessor($commandBus)->process($input, new Post(), ['id' => self::CHANNEL_ID]);

    self::assertSame(self::MEMBER_ID, $output->memberId);
    self::assertSame('moderator', $output->role);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(new AddChannelParticipantInput(), new Post(), []);
  }

  #[Test]
  public function testProcessThrowsWhenBodyIsInvalid(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(null, new Post(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new AddChannelParticipantProcessor(
      $this->createStub(CommandBusPort::class),
      new ChannelOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new AddChannelParticipantInput(), new Post(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsConflictExceptionToHttp409(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingConflictException('Already a participant.'));

    $this->expectException(ConflictHttpException::class);

    $this->createProcessor($commandBus)
      ->process(new AddChannelParticipantInput(), new Post(), ['id' => self::CHANNEL_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): AddChannelParticipantProcessor
  {
    return new AddChannelParticipantProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());
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

  private function participantView(): ParticipantView
  {
    return new ParticipantView(
      conversationId: self::CHANNEL_ID,
      memberId: self::MEMBER_ID,
      role: 'moderator',
      source: 'direct',
      addedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
