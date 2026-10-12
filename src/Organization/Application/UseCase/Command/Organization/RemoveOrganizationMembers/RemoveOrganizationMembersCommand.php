<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationMembers;

use Shared\Application\Message\CommandMessage;

/**
 * Class RemoveOrganizationMembersCommand
 *
 * Defines one durable batch mutation rather than several independently committed route-primary commands.
 *
 * @category Command
 */
final readonly class RemoveOrganizationMembersCommand implements CommandMessage
{
  // #region Constructor
  /**
   * @param string $organizationId current organization scope
   * @param list<string> $memberIds requested target identities in input order
   */
  public function __construct(public string $organizationId, public array $memberIds)
  {
  }
  // #endregion
}
