<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\LeaveOrganization;

use Organization\Application\Port\Inbound\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\Exception\{OrganizationLastAdminException, OrganizationMemberNotFoundException, OrganizationNotFoundException, OrganizationOwnerCannotLeaveException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * UseCase LeaveOrganizationHandler.
 *
 * Lets an active member deactivate their own membership. Two guards are
 * evaluated before deactivation, in this order:
 *
 * 1. The organization's owner cannot leave — they must transfer ownership
 *    first (see TransferOrganizationOwnershipHandler), since leaving would
 *    otherwise strip the organization of its owner entirely.
 * 2. The organization must keep at least one active administrator, enforced
 *    by the same {@see OrganizationLastAdminGuardPort} used by
 *    RemoveOrganizationMember. That check and the deactivation it authorizes
 *    share one transaction: the guard takes a transaction-scoped advisory lock
 *    before its census, so running it outside the write transaction would
 *    release the lock before the write and let two concurrent departures each
 *    read "another administrator remains".
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LeaveOrganizationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the LeaveOrganizationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationLastAdminGuardPort $lastAdminGuard the last-administrator lockout guard port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationLastAdminGuardPort $lastAdminGuard,
    private EventDispatcherPort $eventDispatcher,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Deactivates the acting user's own organization membership (self-removal).
   *
   * @since 1.0.0
   *
   * @param LeaveOrganizationCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the acting user is not
   *                                             an active member of the
   *                                             organization
   * @throws OrganizationOwnerCannotLeaveException when the acting user is the
   *                                               organization's current owner
   * @throws OrganizationLastAdminException when leaving would strip the
   *                                        organization of its last active
   *                                        administrator
   *
   * @return LeaveOrganizationResult the use case result
   */
  public function __invoke(LeaveOrganizationCommand $command): LeaveOrganizationResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $member = $this->memberRepository->findByOrganizationAndUser($organizationId, $command->actingUserId);

    if (null === $member || !$member->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($command->actingUserId, $command->organizationId);
    }

    if ($command->actingUserId === $organization->ownerUserId()) {
      throw OrganizationOwnerCannotLeaveException::mustTransferOwnershipFirst();
    }

    // Reuse the same guard RemoveOrganizationMember relies on: leaving must not
    // strand the organization without an active administrator. The census and
    // the deactivation it authorizes run in one transaction so the guard's
    // advisory lock is still held when the write commits.
    $this->transactionManager->transactional(function () use ($command, $member): void {
      $this->lastAdminGuard->assertCanRemoveMember($command->organizationId, (string) $member->id());

      $member->deactivate();
      $this->memberRepository->save($member);
    });

    $this->eventDispatcher->dispatch(new OrganizationMemberRemovedEvent(
      organizationId: (string) $organizationId,
      memberId: (string) $member->id(),
      userId: $command->actingUserId,
    ));

    return new LeaveOrganizationResult(
      memberId: (string) $member->id(),
      organizationId: (string) $organizationId,
    );
  }
  // #endregion
}
