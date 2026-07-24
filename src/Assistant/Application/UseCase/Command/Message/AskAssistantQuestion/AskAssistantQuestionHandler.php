<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\AskAssistantQuestion;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\Port\Outbound\{AssistantGenerationDispatcherPort, AssistantMessageRepositoryPort, AssistantThreadRepositoryPort};
use Assistant\Domain\Event\Message\AssistantQuestionAskedEvent;
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantThreadId};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * UseCase AskAssistantQuestionHandler.
 *
 * Self-enforces `organization.assistant.use`. Persists the user's message
 * (created already `complete`) and a placeholder `pending` assistant reply
 * for the same turn, bumps the thread's activity timestamp, then enqueues
 * generation through {@see AssistantGenerationDispatcherPort}, passing the
 * thread's own tenant-selected model (validated at thread-creation time —
 * see `StartAssistantThreadHandler`) and this question's optional
 * tenant-selected temperature; the actual Ollama call happens
 * asynchronously in
 * {@see \Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyHandler}.
 * A thread not belonging to BOTH the requested organization and the
 * requesting user resolves to {@see AssistantThreadNotFoundException} (see
 * `GetAssistantThreadHandler`'s docblock).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AskAssistantQuestionHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadRepositoryPort $threads the assistant thread repository port
   * @param AssistantMessageRepositoryPort $messages the assistant message repository port
   * @param AssistantGenerationDispatcherPort $dispatcher the generation dispatcher port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param UuidFactory $uuidFactory the uuid factory
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private AssistantThreadRepositoryPort $threads,
    private AssistantMessageRepositoryPort $messages,
    private AssistantGenerationDispatcherPort $dispatcher,
    private OrganizationAuthorizationPort $authorization,
    private UuidFactory $uuidFactory,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param AskAssistantQuestionCommand $command the command payload
   *
   * @return AskAssistantQuestionResult the use case result
   */
  public function __invoke(AskAssistantQuestionCommand $command): AskAssistantQuestionResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.assistant.use',
    ]);

    $thread = $this->threads->findById(AssistantThreadId::fromString($command->threadId));

    if (null === $thread || !$thread->belongsTo($command->organizationId, $command->actorUserId)) {
      throw AssistantThreadNotFoundException::withId($command->threadId);
    }

    $now = $this->clock->now();

    /** @var AssistantMessageId $userMessageId */
    $userMessageId = $this->uuidFactory->create(AssistantMessageId::class);

    $userMessage = AssistantMessage::askUser(
      id: $userMessageId,
      threadId: $command->threadId,
      organizationId: $command->organizationId,
      body: $command->body,
      now: $now,
    );

    $this->messages->save($userMessage);

    /** @var AssistantMessageId $assistantMessageId */
    $assistantMessageId = $this->uuidFactory->create(AssistantMessageId::class);

    $assistantMessage = AssistantMessage::pendingReply(
      id: $assistantMessageId,
      threadId: $command->threadId,
      organizationId: $command->organizationId,
      now: $now,
    );

    $this->messages->save($assistantMessage);

    $thread->recordActivity($now);
    $this->threads->save($thread);

    $this->dispatcher->enqueue(
      organizationId: $command->organizationId,
      threadId: $command->threadId,
      userMessageId: (string) $userMessageId,
      assistantMessageId: (string) $assistantMessageId,
      model: $thread->model(),
      temperature: $command->temperature,
    );

    $this->eventDispatcher->dispatch(new AssistantQuestionAskedEvent(
      organizationId: $command->organizationId,
      threadId: $command->threadId,
      memberId: $command->actorUserId,
      userMessageId: (string) $userMessageId,
      assistantMessageId: (string) $assistantMessageId,
    ));

    return new AskAssistantQuestionResult(
      threadId: $command->threadId,
      organizationId: $command->organizationId,
      userMessage: AssistantMessageView::fromDomain($userMessage),
      assistantMessage: AssistantMessageView::fromDomain($assistantMessage),
    );
  }
  // #endregion
}
