<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\UseCase\Command\Channel\CreateChannel\{
  CreateChannelCommand,
  CreateChannelResult
};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingValidationException};
use Messaging\Presentation\Api\Dto\Input\Channel\CreateChannelInput;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Processor\Channel\CreateChannelProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  UnprocessableEntityHttpException
};

/**
 * Test CreateChannelProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateChannelProcessor::class)]
final class CreateChannelProcessorTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441100';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesTheCreateCommandAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (CreateChannelCommand $command): bool => self::USER_ID === $command->userId
        && self::ORGANIZATION_ID === $command->organizationId
        && 'General' === $command->name))
      ->willReturn(new CreateChannelResult($this->view()));

    $input = new CreateChannelInput();
    $input->organization = '/api/organizations/' . self::ORGANIZATION_ID;
    $input->name = 'General';

    $output = $this->createProcessor($commandBus)->process($input, new Post());

    self::assertSame(self::CHANNEL_ID, $output->id);
    self::assertSame('General', $output->name);
  }

  #[Test]
  public function testProcessThrowsWhenBodyIsInvalid(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))->process(null, new Post());
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new CreateChannelProcessor(
      $this->createStub(CommandBusPort::class),
      new ChannelOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new CreateChannelInput(), new Post());
  }

  #[Test]
  public function testProcessMapsValidationExceptionToHttp422(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingValidationException('Name is required.'));

    $input = new CreateChannelInput();
    $input->organization = '/api/organizations/' . self::ORGANIZATION_ID;

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createProcessor($commandBus)->process($input, new Post());
  }

  #[Test]
  public function testProcessMapsAccessDeniedExceptionToHttp403(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingAccessDeniedException('Not a member.'));

    $input = new CreateChannelInput();
    $input->organization = '/api/organizations/' . self::ORGANIZATION_ID;

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProcessor($commandBus)->process($input, new Post());
  }

  private function createProcessor(CommandBusPort $commandBus): CreateChannelProcessor
  {
    return new CreateChannelProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());
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
      self::ORGANIZATION_ID,
      'General',
      null,
      'creator-1',
      1,
      false,
      null,
      0,
      $now,
      $now,
      null,
    );
  }
  // #endregion
}
