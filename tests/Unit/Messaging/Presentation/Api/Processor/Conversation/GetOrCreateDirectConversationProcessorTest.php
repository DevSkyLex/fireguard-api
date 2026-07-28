<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation\{GetOrCreateDirectConversationCommand, GetOrCreateDirectConversationResult};
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Presentation\Api\Dto\Input\GetOrCreateDirectConversationInput;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Processor\Conversation\GetOrCreateDirectConversationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

/**
 * Test GetOrCreateDirectConversationProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrCreateDirectConversationProcessor::class)]
final class GetOrCreateDirectConversationProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440100';

  private const string OTHER_MEMBER_ID = '550e8400-e29b-41d4-a716-446655440200';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440300';

  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (GetOrCreateDirectConversationCommand $command): bool => self::USER_ID === $command->userId
        && self::ORG_ID === $command->organizationId
        && self::OTHER_MEMBER_ID === $command->otherMemberId))
      ->willReturn(new GetOrCreateDirectConversationResult($this->view()));

    $processor = new GetOrCreateDirectConversationProcessor($commandBus, new ConversationOutputFactory(), $this->securityWithUser());

    $input = new GetOrCreateDirectConversationInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->memberId = self::OTHER_MEMBER_ID;

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(ConversationOutput::class, $output);
    self::assertSame('direct', $output->subjectType);
  }

  #[Test]
  public function testProcessThrowsWhenTheRequestBodyIsInvalid(): void
  {
    $processor = new GetOrCreateDirectConversationProcessor($this->createStub(CommandBusPort::class), new ConversationOutputFactory(), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post());
  }

  #[Test]
  public function testProcessMapsAValidationException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingValidationException('Cannot start a direct conversation with yourself.'));

    $processor = new GetOrCreateDirectConversationProcessor($commandBus, new ConversationOutputFactory(), $this->securityWithUser());

    $input = new GetOrCreateDirectConversationInput();
    $input->organization = '/api/organizations/' . self::ORG_ID;
    $input->memberId = self::OTHER_MEMBER_ID;

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new GetOrCreateDirectConversationProcessor($this->createStub(CommandBusPort::class), new ConversationOutputFactory(), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), []);
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

  private function view(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView('550e8400-e29b-41d4-a716-446655440400', self::ORG_ID, 'direct', 'deadbeefdeadbeefdeadbeefdeadbeef', 'participants', null, 0, false, $now, $now);
  }
}
