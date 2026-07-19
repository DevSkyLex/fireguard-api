<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Thread\StartAssistantThread;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort;
use Assistant\Domain\Event\Thread\AssistantThreadStartedEvent;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\Service\AssistantModelPolicy;
use Assistant\Domain\ValueObject\AssistantThreadId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * UseCase StartAssistantThreadHandler.
 *
 * Self-enforces `organization.assistant.use`. When `$command->model` is
 * supplied, re-validates it against {@see AssistantModelPolicy} — the
 * authoritative gate behind
 * `Presentation\Api\Validator\ValidAssistantModel\ValidAssistantModelValidator`'s
 * UX-only input-time check (mirrors `CreateWebhookSubscriptionHandler`'s use
 * of `WebhookUrlPolicy`) — before it is ever persisted on the thread.
 *
 * **Member identity note (L2.1 scope)**: this handler keys the new thread's
 * `memberId` on the authenticated user's id (`$command->actorUserId`) rather
 * than a resolved `Organization\Domain\ValueObject\OrganizationMemberId`.
 * Resolving the true membership identifier the way
 * `Approval\Application\Port\Outbound\ApprovalMemberDirectoryPort` /
 * `Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort` do would
 * require a new adapter hosted in `Organization\Infrastructure\Adapter\Assistant\`
 * — a cross-module edit outside this lot's "Assistant module only" scope.
 * Since an organization membership is 1:1 per (user, organization), using
 * the user id as the thread-scoping key preserves this module's core
 * invariant — a member reads/uses only their OWN threads — without that
 * adapter. See `src/Assistant/MODULE.md` ("Deferred cross-module work").
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartAssistantThreadHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadRepositoryPort $threads the assistant thread repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param AssistantModelPolicy $modelPolicy the tenant model allowlist policy
   * @param UuidFactory $uuidFactory the uuid factory
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private AssistantThreadRepositoryPort $threads,
    private OrganizationAuthorizationPort $authorization,
    private AssistantModelPolicy $modelPolicy,
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
   * @param StartAssistantThreadCommand $command the command payload
   *
   * @return StartAssistantThreadResult the use case result
   */
  public function __invoke(StartAssistantThreadCommand $command): StartAssistantThreadResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      'organization.assistant.use',
    ]);

    if (null !== $command->model && '' !== $command->model) {
      $this->modelPolicy->assertAllowed($command->model);
    }

    $memberId = $command->actorUserId;

    /** @var AssistantThreadId $id */
    $id = $this->uuidFactory->create(AssistantThreadId::class);

    $thread = AssistantThread::start(
      id: $id,
      organizationId: $command->organizationId,
      memberId: $memberId,
      title: $command->title,
      now: $this->clock->now(),
      model: $command->model,
    );

    $this->threads->save($thread);

    $this->eventDispatcher->dispatch(new AssistantThreadStartedEvent(
      organizationId: $command->organizationId,
      threadId: (string) $id,
      memberId: $memberId,
    ));

    return new StartAssistantThreadResult(AssistantThreadView::fromDomain($thread));
  }
  // #endregion
}
