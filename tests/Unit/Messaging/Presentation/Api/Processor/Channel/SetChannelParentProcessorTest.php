<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\UseCase\Command\Channel\SetChannelParent\{SetChannelParentCommand, SetChannelParentResult};
use Messaging\Domain\Exception\{MessagingConflictException, MessagingNotFoundException, MessagingValidationException};
use Messaging\Presentation\Api\Dto\Input\Channel\SetChannelParentInput;
use Messaging\Presentation\Api\Dto\Output\ChannelOutput;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Processor\Channel\SetChannelParentProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

/**
 * Test SetChannelParentProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetChannelParentProcessor::class)]
final class SetChannelParentProcessorTest extends TestCase
{
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655441401';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProcessDispatchesTheSetParentCommandAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (SetChannelParentCommand $command): bool => self::USER_ID === $command->userId
        && self::CHANNEL_ID === $command->conversationId
        && self::PARENT_ID === $command->parentConversationId))
      ->willReturn(new SetChannelParentResult($this->view(self::PARENT_ID)));

    $processor = new SetChannelParentProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());

    $input = new SetChannelParentInput();
    $input->parentChannelId = self::PARENT_ID;

    $output = $processor->process($input, new Patch(), ['id' => self::CHANNEL_ID]);

    self::assertInstanceOf(ChannelOutput::class, $output);
    self::assertSame('/api/channels/' . self::PARENT_ID, $output->parent);
  }

  #[Test]
  public function testProcessDispatchesNullToDetachTheParent(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (SetChannelParentCommand $command): bool => null === $command->parentConversationId))
      ->willReturn(new SetChannelParentResult($this->view(null)));

    $processor = new SetChannelParentProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());

    $output = $processor->process(new SetChannelParentInput(), new Patch(), ['id' => self::CHANNEL_ID]);

    self::assertNull($output->parent);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $processor = new SetChannelParentProcessor($this->createStub(CommandBusPort::class), new ChannelOutputFactory(), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new SetChannelParentInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessThrowsWhenBodyIsInvalid(): void
  {
    $processor = new SetChannelParentProcessor($this->createStub(CommandBusPort::class), new ChannelOutputFactory(), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Patch(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::conversation(self::CHANNEL_ID));

    $processor = new SetChannelParentProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());

    $this->expectException(NotFoundHttpException::class);

    $processor->process(new SetChannelParentInput(), new Patch(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsConflictExceptionToHttp409(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingConflictException('A channel cannot be its own parent.'));

    $processor = new SetChannelParentProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());

    $this->expectException(ConflictHttpException::class);

    $processor->process(new SetChannelParentInput(), new Patch(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsValidationExceptionToHttp422(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingValidationException('The parent must be an existing channel.'));

    $processor = new SetChannelParentProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process(new SetChannelParentInput(), new Patch(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new SetChannelParentProcessor($this->createStub(CommandBusPort::class), new ChannelOutputFactory(), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Patch(), []);
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

  private function view(?string $parentChannelId): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(self::CHANNEL_ID, 'org-1', 'General', null, 'creator-1', 1, false, null, 0, $now, $now, $parentChannelId);
  }
}
