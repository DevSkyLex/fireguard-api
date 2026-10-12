<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ListOrganizationInvitations;

use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\GetOrganizationInvitationResult;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\ResultMessage;

/**
 * Class ListOrganizationInvitationsResult
 *
 * Captures invitation expiration and inviter presentation identities before the same final commit.
 *
 * @category Result
 */
final readonly class ListOrganizationInvitationsResult implements ResultMessage
{
  /**
   * @param PaginatedResult<GetOrganizationInvitationResult> $page original authorized listing
   * @param array<string,string|null> $inviterDisplayNames original names and missing-account fallbacks
   */
  public function __construct(public PaginatedResult $page, public array $inviterDisplayNames)
  {
  }
}
