<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\UseCase\Command\Channel\UpdateChannel\{
  UpdateChannelCommand,
  UpdateChannelResult
};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Input\Channel\UpdateChannelInput;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Processor\Channel\UpdateChannelProcessor;
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
 * Test UpdateChannelProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateChannelProcessor::class)]
final class UpdateChannelProcessorTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441100';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesTheUpdateCommandAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (UpdateChannelCommand $command): bool => self::USER_ID === $command->userId
        && self::CHANNEL_ID === $command->conversationId
        && 'Renamed' === $command->name
        && true === $command->isArchived))
      ->willReturn(new UpdateChannelResult($this->view(true)));

    $input = new UpdateChannelInput();
    $input->name = 'Renamed';
    $input->isArchived = true;

    $output = $this->createProcessor($commandBus)->process($input, new Patch(), ['id' => self::CHANNEL_ID]);

    self::assertSame(self::CHANNEL_ID, $output->id);
    self::assertTrue($output->isArchived);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(new UpdateChannelInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessThrowsWhenBodyIsInvalid(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(null, new Patch(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new UpdateChannelProcessor(
      $this->createStub(CommandBusPort::class),
      new ChannelOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new UpdateChannelInput(), new Patch(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::conversation(self::CHANNEL_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(new UpdateChannelInput(), new Patch(), ['id' => self::CHANNEL_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): UpdateChannelProcessor
  {
    return new UpdateChannelProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());
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

  private function view(bool $isArchived): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(
      self::CHANNEL_ID,
      self::ORGANIZATION_ID,
      'Renamed',
      null,
      'creator-1',
      1,
      $isArchived,
      null,
      0,
      $now,
      $now,
      null,
    );
  }
  // #endregion
}
