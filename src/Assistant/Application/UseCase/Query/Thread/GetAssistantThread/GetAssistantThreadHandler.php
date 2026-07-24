<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\GetAssistantThread;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\Port\Outbound\{AssistantMessageRepositoryPort, AssistantThreadRepositoryPort};
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Domain\ValueObject\AssistantThreadId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function max;

/**
 * UseCase GetAssistantThreadHandler.
 *
 * Self-enforces `organization.assistant.use`. A thread not belonging to
 * BOTH the requested organization and the requesting user (see
 * `StartAssistantThreadHandler`'s docblock for the `memberId` = `actorUserId`
 * scoping note) resolves to {@see AssistantThreadNotFoundException} —
 * information hiding, never a distinct access-denied case: a member may
 * only ever read their OWN assistant threads.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetAssistantThreadHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadRepositoryPort $threads the assistant thread repository port
   * @param AssistantMessageRepositoryPort $messages the assistant message repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private AssistantThreadRepositoryPort $threads,
    private AssistantMessageRepositoryPort $messages,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetAssistantThreadQuery $query the query payload
   *
   * @return GetAssistantThreadResult the query result
   */
  public function __invoke(GetAssistantThreadQuery $query): GetAssistantThreadResult
  {
    $this->authorization->assertGrantedPermissions($query->actorUserId, $query->organizationId, [
      'organization.assistant.use',
    ]);

    $thread = $this->threads->findById(AssistantThreadId::fromString($query->threadId));

    if (null === $thread || !$thread->belongsTo($query->organizationId, $query->actorUserId)) {
      throw AssistantThreadNotFoundException::withId($query->threadId);
    }

    $itemsPerPage = max(1, $query->messagesItemsPerPage);
    $offset = max(0, $query->messagesPage - 1) * $itemsPerPage;

    $messages = $this->messages->listByThread($query->threadId, $itemsPerPage, $offset);
    $total = $this->messages->countByThread($query->threadId);

    return new GetAssistantThreadResult(
      thread: AssistantThreadView::fromDomain($thread),
      messages: array_map(AssistantMessageView::fromDomain(...), $messages),
      messagesPage: $query->messagesPage,
      messagesItemsPerPage: $itemsPerPage,
      messagesTotal: $total,
    );
  }
  // #endregion
}
