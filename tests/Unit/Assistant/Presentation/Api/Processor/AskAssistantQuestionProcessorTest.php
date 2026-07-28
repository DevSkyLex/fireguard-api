<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Processor;

use ApiPlatform\Metadata\Post;
use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\{AskAssistantQuestionCommand, AskAssistantQuestionResult};
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Presentation\Api\Dto\Input\AskAssistantQuestionInput;
use Assistant\Presentation\Api\Factory\AssistantMessageOutputFactory;
use Assistant\Presentation\Api\Processor\AskAssistantQuestionProcessor;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test AskAssistantQuestionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AskAssistantQuestionProcessor::class)]
final class AskAssistantQuestionProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string THREAD_ID = 'thread-1';

  private const string USER_ID = 'user-id';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesTheCommandAndReturnsBothMessages(): void
  {
    $input = new AskAssistantQuestionInput();
    $input->body = 'When is the next extinguisher check?';
    $input->temperature = 0.4;

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (AskAssistantQuestionCommand $command) use (&$captured): AskAssistantQuestionResult {
        $captured = $command;

        return new AskAssistantQuestionResult(
          threadId: self::THREAD_ID,
          organizationId: self::ORGANIZATION_ID,
          userMessage: $this->view('message-user', 'user', 'When is the next extinguisher check?'),
          assistantMessage: $this->view('message-assistant', 'assistant', 'In March.'),
        );
      });

    $processor = new AskAssistantQuestionProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new AssistantMessageOutputFactory(),
    );

    $output = $processor->process($input, new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);

    self::assertInstanceOf(AskAssistantQuestionCommand::class, $captured);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::THREAD_ID, $captured->threadId);
    self::assertSame(self::USER_ID, $captured->actorUserId);
    self::assertSame('When is the next extinguisher check?', $captured->body);
    self::assertSame(0.4, $captured->temperature);

    self::assertSame(self::THREAD_ID, $output->threadId);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame('message-user', $output->userMessage->id);
    self::assertSame('user', $output->userMessage->role);
    self::assertSame('message-assistant', $output->assistantMessage->id);
    self::assertSame('In March.', $output->assistantMessage->body);
  }

  #[Test]
  public function testProcessRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new AskAssistantQuestionProcessor(
      $this->createStub(CommandBusPort::class),
      $security,
      new AssistantMessageOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new AskAssistantQuestionInput(), new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);
  }

  #[Test]
  public function testProcessRequiresAThreadIdUriVariable(): void
  {
    $processor = new AskAssistantQuestionProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
      new AssistantMessageOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new AskAssistantQuestionInput(), new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
    ]);
  }

  #[Test]
  public function testProcessRejectsABlankOrganizationId(): void
  {
    $processor = new AskAssistantQuestionProcessor(
      $this->createStub(CommandBusPort::class),
      $this->authenticatedSecurity(),
      new AssistantMessageOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new AskAssistantQuestionInput(), new Post(), [
      'organizationId' => '',
      'threadId' => self::THREAD_ID,
    ]);
  }

  #[Test]
  public function testProcessMapsDomainExceptions(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      AssistantThreadNotFoundException::withId(self::THREAD_ID),
    );

    $processor = new AskAssistantQuestionProcessor(
      $commandBus,
      $this->authenticatedSecurity(),
      new AssistantMessageOutputFactory(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(new AskAssistantQuestionInput(), new Post(), [
      'organizationId' => self::ORGANIZATION_ID,
      'threadId' => self::THREAD_ID,
    ]);
  }
  // #endregion

  // #region Helpers
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }

  private function view(string $id, string $role, string $body): AssistantMessageView
  {
    return new AssistantMessageView(
      id: $id,
      threadId: self::THREAD_ID,
      organizationId: self::ORGANIZATION_ID,
      role: $role,
      body: $body,
      status: 'complete',
      errorCode: null,
      tokenCount: 12,
      createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
      completedAt: new DateTimeImmutable('2026-07-18T00:00:02+00:00'),
    );
  }
  // #endregion
}
