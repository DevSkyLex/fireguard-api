<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ListOrganizationInvitations;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\CommandMessage;

/**
 * Class ListOrganizationInvitationsCommand
 *
 * Declares the stateful listing that expires old invitations as one main transaction and immutable response receipt.
 *
 * @category Command
 */
final readonly class ListOrganizationInvitationsCommand implements CommandMessage
{
  /**
   * @param string $organizationId requested organization scope
   * @param string $userId authenticated listing actor
   * @param Pagination $pagination original requested page
   */
  public function __construct(public string $organizationId, public string $userId, public Pagination $pagination = new Pagination())
  {
  }
}
