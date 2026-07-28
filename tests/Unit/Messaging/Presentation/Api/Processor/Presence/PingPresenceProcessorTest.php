<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Presence;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use DateTimeInterface;
use Messaging\Application\UseCase\Command\Presence\PingPresence\{PingPresenceCommand, PingPresenceResult};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Messaging\Presentation\Api\Dto\Input\PingPresenceInput;
use Messaging\Presentation\Api\Dto\Output\PingPresenceOutput;
use Messaging\Presentation\Api\Processor\Presence\PingPresenceProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Test PingPresenceProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PingPresenceProcessor::class)]
final class PingPresenceProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440100';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440300';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440400';

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheOutput(): void
  {
    $lastSeenAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (PingPresenceCommand $command): bool => self::USER_ID === $command->userId
        && self::ORG_ID === $command->organizationId))
      ->willReturn(new PingPresenceResult(self::MEMBER_ID, $lastSeenAt));

    $processor = new PingPresenceProcessor($commandBus, $this->securityWithUser());

    $input = new PingPresenceInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(PingPresenceOutput::class, $output);
    self::assertSame(self::MEMBER_ID, $output->memberId);
    self::assertSame($lastSeenAt->format(DateTimeInterface::ATOM), $output->lastSeenAt);
  }

  #[Test]
  public function testProcessThrowsWhenTheRequestBodyIsInvalid(): void
  {
    $processor = new PingPresenceProcessor($this->createStub(CommandBusPort::class), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessMapsAnAccessDeniedException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingAccessDeniedException('Not an active member.'));

    $processor = new PingPresenceProcessor($commandBus, $this->securityWithUser());

    $input = new PingPresenceInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessLetsAPingThroughWhileTheRateLimiterStillAcceptsIt(): void
  {
    $lastSeenAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new PingPresenceResult(self::MEMBER_ID, $lastSeenAt));

    $processor = new PingPresenceProcessor(
      $commandBus,
      $this->securityWithUser(),
      $this->createRateLimiterFactory(limit: 5),
    );

    $input = new PingPresenceInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(PingPresenceOutput::class, $output);
    self::assertSame(self::MEMBER_ID, $output->memberId);
  }

  #[Test]
  public function testProcessThrowsTooManyRequestsWhenRateLimited(): void
  {
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create('messaging_presence_ping_' . self::USER_ID . '_' . self::ORG_ID)->consume();

    $processor = new PingPresenceProcessor($this->createStub(CommandBusPort::class), $this->securityWithUser(), $rateLimiter);

    $input = new PingPresenceInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;

    $this->expectException(TooManyRequestsHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new PingPresenceProcessor($this->createStub(CommandBusPort::class), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), []);
  }

  private function createRateLimiterFactory(int $limit): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'messaging_presence_ping',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
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
}
