<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation\{
  GetOrCreateConversationCommand,
  GetOrCreateConversationResult
};
use Messaging\Domain\Exception\MessagingSubjectNotFoundException;
use Messaging\Presentation\Api\Dto\Input\GetOrCreateConversationInput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Processor\Conversation\GetOrCreateConversationProcessor;
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
 * Test GetOrCreateConversationProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrCreateConversationProcessor::class)]
final class GetOrCreateConversationProcessorTest extends TestCase
{
  // #region Constants
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441100';

  private const string SUBJECT_ID = '550e8400-e29b-41d4-a716-446655441600';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessParsesTheIrisAndDispatchesTheCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (GetOrCreateConversationCommand $command): bool => self::USER_ID === $command->userId
        && self::ORGANIZATION_ID === $command->organizationId
        && 'facility' === $command->subjectType
        && self::SUBJECT_ID === $command->subjectId))
      ->willReturn(new GetOrCreateConversationResult($this->view(), 'Main Site'));

    $output = $this->createProcessor($commandBus)->process($this->input(), new Post());

    self::assertSame(self::CONVERSATION_ID, $output->id);
    self::assertSame('Main Site', $output->subjectLabel);
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

    $processor = new GetOrCreateConversationProcessor(
      $this->createStub(CommandBusPort::class),
      new ConversationOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessMapsSubjectNotFoundExceptionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      new MessagingSubjectNotFoundException('Subject not found.'),
    );

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process($this->input(), new Post());
  }

  private function input(): GetOrCreateConversationInput
  {
    $input = new GetOrCreateConversationInput();
    $input->organization = '/api/organizations/' . self::ORGANIZATION_ID;
    $input->subjectType = 'facility';
    $input->subject = '/api/facilities/' . self::SUBJECT_ID;

    return $input;
  }

  private function createProcessor(CommandBusPort $commandBus): GetOrCreateConversationProcessor
  {
    return new GetOrCreateConversationProcessor(
      $commandBus,
      new ConversationOutputFactory(),
      $this->securityWithUser(),
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

  private function view(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(
      self::CONVERSATION_ID,
      self::ORGANIZATION_ID,
      'facility',
      self::SUBJECT_ID,
      'subject',
      null,
      1,
      false,
      $now,
      $now,
    );
  }
  // #endregion
}
