<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Channel;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\UseCase\Command\Channel\BindChannelTeam\{
  BindChannelTeamCommand,
  BindChannelTeamResult
};
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Presentation\Api\Dto\Input\Channel\BindChannelTeamInput;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Processor\Channel\BindChannelTeamProcessor;
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
 * Test BindChannelTeamProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(BindChannelTeamProcessor::class)]
final class BindChannelTeamProcessorTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441100';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655441500';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessBindsTheTeamAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (BindChannelTeamCommand $command): bool => self::USER_ID === $command->userId
        && self::CHANNEL_ID === $command->conversationId
        && self::TEAM_ID === $command->teamId))
      ->willReturn(new BindChannelTeamResult($this->view(self::TEAM_ID)));

    $input = new BindChannelTeamInput();
    $input->teamId = self::TEAM_ID;

    $output = $this->createProcessor($commandBus)->process($input, new Patch(), ['id' => self::CHANNEL_ID]);

    self::assertSame(
      '/api/organizations/' . self::ORGANIZATION_ID . '/teams/' . self::TEAM_ID,
      $output->team,
    );
  }

  #[Test]
  public function testProcessUnbindsTheTeamWhenTheInputIsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (BindChannelTeamCommand $command): bool => null === $command->teamId))
      ->willReturn(new BindChannelTeamResult($this->view(null)));

    $output = $this->createProcessor($commandBus)
      ->process(new BindChannelTeamInput(), new Patch(), ['id' => self::CHANNEL_ID]);

    self::assertNull($output->team);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(new BindChannelTeamInput(), new Patch(), []);
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

    $processor = new BindChannelTeamProcessor(
      $this->createStub(CommandBusPort::class),
      new ChannelOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new BindChannelTeamInput(), new Patch(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProcessMapsValidationExceptionToHttp422(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingValidationException('Team does not belong to the organization.'));

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createProcessor($commandBus)
      ->process(new BindChannelTeamInput(), new Patch(), ['id' => self::CHANNEL_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): BindChannelTeamProcessor
  {
    return new BindChannelTeamProcessor($commandBus, new ChannelOutputFactory(), $this->securityWithUser());
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

  private function view(?string $teamId): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(
      self::CHANNEL_ID,
      self::ORGANIZATION_ID,
      'General',
      $teamId,
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
