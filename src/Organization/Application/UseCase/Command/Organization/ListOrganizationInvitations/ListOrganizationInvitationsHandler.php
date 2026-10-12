<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ListOrganizationInvitations;

use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\{GetOrganizationInvitationResult, ListOrganizationInvitationsQuery};
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;
use User\Application\Port\Inbound\UserDirectoryPort;

use function array_unique;
use function array_values;
use function trim;

/**
 * Class ListOrganizationInvitationsHandler
 *
 * Runs the owner expiration read inside the route-primary main transaction and preserves the original presentation fields.
 *
 * @category Handler
 */
final readonly class ListOrganizationInvitationsHandler implements CommandHandler
{
  /**
   * @param QueryBusPort $queries existing owner listing and expiration behavior
   * @param OrganizationAuthorizationPort $authorization original actual registered manager gate
   * @param UserDirectoryPort $users authorized inviter presentation identities
   */
  public function __construct(private QueryBusPort $queries, private OrganizationAuthorizationPort $authorization, private UserDirectoryPort $users)
  {
  }

  /**
   * @param ListOrganizationInvitationsCommand $command original actor and page
   *
   * @return ListOrganizationInvitationsResult durable original listing
   */
  public function __invoke(ListOrganizationInvitationsCommand $command): ListOrganizationInvitationsResult
  {
    if (!$this->authorization->hasPermission($command->userId, $command->organizationId, 'organization.members.manage')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.members.manage');
    }
    /** @var PaginatedResult<GetOrganizationInvitationResult> $page */
    $page = $this->queries->ask(new ListOrganizationInvitationsQuery($command->organizationId, $command->pagination));
    $names = [];
    $ids = [];
    foreach ($page->items as $invitation) {
      $ids[] = $invitation->invitedByUserId;
    }

    try {
      foreach ($this->users->findByIds(array_values(array_unique($ids))) as $id => $user) {
        $names[$id] = trim($user->firstName . ' ' . $user->lastName) ?: ($user->username ?: null);
      }
    } catch (Throwable) {
      // The existing listing tolerates unavailable inviter presentation data.
    }

    return new ListOrganizationInvitationsResult($page, $names);
  }
}
