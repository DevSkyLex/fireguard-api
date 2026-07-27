<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\UseCase\Command\Channel\UpdateChannel\{
  UpdateChannelCommand,
  UpdateChannelResult
};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Messaging\Presentation\Api\Processor\Channel\DeleteChannelProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
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
 * Test DeleteChannelProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteChannelProcessor::class)]
final class DeleteChannelProcessorTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessArchivesTheChannelAndReturnsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (UpdateChannelCommand $command): bool => self::USER_ID === $command->userId
        && self::CHANNEL_ID === $command->conversationId
        && true === $command->isArchived))
      ->willReturn(new UpdateChannelResult($this->view()));

    $this->createProcessor($commandBus)->process(null, new Delete(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))->process(null, new Delete(), []);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DeleteChannelProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::conversation(self::CHANNEL_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsAccessDeniedExceptionToHttp403(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingAccessDeniedException('Not allowed.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), ['id' => self::CHANNEL_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): DeleteChannelProcessor
  {
    return new DeleteChannelProcessor($commandBus, $this->securityWithUser());
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

  private function view(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(
      self::CHANNEL_ID,
      '550e8400-e29b-41d4-a716-446655441100',
      'General',
      null,
      'creator-1',
      1,
      true,
      null,
      0,
      $now,
      $now,
      null,
    );
  }
  // #endregion
}
