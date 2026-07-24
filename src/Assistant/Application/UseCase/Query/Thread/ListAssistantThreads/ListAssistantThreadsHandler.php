<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\ListAssistantThreads;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function max;

/**
 * UseCase ListAssistantThreadsHandler.
 *
 * Self-enforces `organization.assistant.use`. Lists only the requesting
 * user's OWN threads (see `StartAssistantThreadHandler`'s docblock for the
 * `memberId` = `actorUserId` scoping note) — there is no shared/
 * organization-wide assistant thread in this design.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListAssistantThreadsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadRepositoryPort $threads the assistant thread repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private AssistantThreadRepositoryPort $threads,
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
   * @param ListAssistantThreadsQuery $query the query payload
   *
   * @return ListAssistantThreadsResult the query result
   */
  public function __invoke(ListAssistantThreadsQuery $query): ListAssistantThreadsResult
  {
    $this->authorization->assertGrantedPermissions($query->actorUserId, $query->organizationId, [
      'organization.assistant.use',
    ]);

    $memberId = $query->actorUserId;

    $itemsPerPage = max(1, $query->itemsPerPage);
    $offset = max(0, $query->page - 1) * $itemsPerPage;

    $threads = $this->threads->listByOrganizationAndMember($query->organizationId, $memberId, $itemsPerPage, $offset);
    $total = $this->threads->countByOrganizationAndMember($query->organizationId, $memberId);

    return new ListAssistantThreadsResult(
      items: array_map(AssistantThreadView::fromDomain(...), $threads),
      page: $query->page,
      itemsPerPage: $itemsPerPage,
      total: $total,
    );
  }
  // #endregion
}
