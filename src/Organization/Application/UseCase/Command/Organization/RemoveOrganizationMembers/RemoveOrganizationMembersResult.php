<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationMembers;

use Shared\Application\Message\ResultMessage;

/**
 * Class RemoveOrganizationMembersResult
 *
 * Preserves the successful and missing-target outcomes with the same final batch commit.
 *
 * @category Result
 */
final readonly class RemoveOrganizationMembersResult implements ResultMessage
{
  // #region Constructor
  /**
   * @param list<string> $removedIds successfully removed target identities
   * @param list<string> $failedIds absent or foreign target identities
   */
  public function __construct(public array $removedIds, public array $failedIds)
  {
  }
  // #endregion
}
