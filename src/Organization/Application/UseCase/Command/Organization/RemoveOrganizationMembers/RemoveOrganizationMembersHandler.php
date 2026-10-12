<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationMembers;

use Organization\Application\Port\Inbound\Internal\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\Internal\OrganizationMemberRepositoryPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember\RemoveOrganizationMemberCommand;
use Organization\Domain\ValueObject\OrganizationMemberId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Class RemoveOrganizationMembersHandler
 *
 * Locks and validates the batch before mutation; missing targets are classified before entering a single-member transaction.
 * Successful removals and the receipt commit together, and a last-administrator refusal rolls back the batch.
 *
 * @category Handler
 */
final readonly class RemoveOrganizationMembersHandler implements CommandHandler
{
  // #region Constructor
  /**
   * @param OrganizationLastAdminGuardPort $guard current serialized lockout invariant
   * @param OrganizationMemberRepositoryPort $members owner membership targets
   * @param CommandBusPort $commands existing nested single-target invariant and events
   * @param TransactionManagerPort $transactions explicitly selected main transaction manager
   */
  public function __construct(private OrganizationLastAdminGuardPort $guard, private OrganizationMemberRepositoryPort $members, private CommandBusPort $commands, private TransactionManagerPort $transactions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @param RemoveOrganizationMembersCommand $command requested batch
   *
   * @return RemoveOrganizationMembersResult exact durable per-target outcome
   */
  public function __invoke(RemoveOrganizationMembersCommand $command): RemoveOrganizationMembersResult
  {
    return $this->transactions->transactional(function () use ($command): RemoveOrganizationMembersResult {
      if ([] !== $command->memberIds) {
        $this->guard->assertCanRemoveMember($command->organizationId, $command->memberIds[0]);
      }
      $this->guard->assertCanRemoveMembers($command->organizationId, $command->memberIds);
      $removed = [];
      $failed = [];
      foreach ($command->memberIds as $memberId) {
        $member = $this->members->findById(OrganizationMemberId::fromString($memberId));
        if (null === $member || (string) $member->organizationId() !== $command->organizationId) {
          $failed[] = $memberId;

          continue;
        }
        $this->commands->dispatch(new RemoveOrganizationMemberCommand($command->organizationId, $memberId));
        $removed[] = $memberId;
      }

      return new RemoveOrganizationMembersResult($removed, $failed);
    });
  }
  // #endregion
}
