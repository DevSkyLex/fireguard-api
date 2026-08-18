<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ReactivateOrganizationMember;

use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Domain\Event\Member\OrganizationMemberAddedEvent;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationMemberNotFoundException, OrganizationMemberNotInactiveException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * UseCase ReactivateOrganizationMemberHandler.
 *
 * Reactivates a member that was previously deactivated (removed). Today
 * reactivation otherwise only happens as a side effect of re-adding the
 * member (see AddOrganizationMemberHandler's reactivation branch, whose
 * OrganizationMemberAddedEvent dispatch this mirrors); this use case exposes
 * it as an explicit action.
 *
 * Reactivating a member brings the organization's active-member count back
 * up exactly like AddOrganizationMemberHandler's re-add path does, so it is
 * gated by the same plan cap: {@see OrganizationQuotaPort::assertCanAdd()},
 * called inside the transaction that persists the reactivation so its
 * advisory lock actually serializes concurrent reactivations/additions
 * (TOCTOU) — see that port's docblock.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReactivateOrganizationMemberHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ReactivateOrganizationMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationQuotaPort $quota the organization quota enforcement port
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationQuotaPort $quota,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Reactivates an inactive organization member.
   *
   * @since 1.0.0
   *
   * @param ReactivateOrganizationMemberCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationArchivedException when the organization is archived
   * @throws OrganizationMemberNotFoundException when the member does not
   *                                             belong to the organization
   * @throws OrganizationMemberNotInactiveException when the member is not
   *                                                currently inactive
   * @throws \Organization\Application\Contract\Quota\OrganizationQuotaExceededException
   *                                                                                     when reactivating would exceed the plan's member cap
   *
   * @return ReactivateOrganizationMemberResult the use case result
   */
  public function __invoke(ReactivateOrganizationMemberCommand $command): ReactivateOrganizationMemberResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    if ($organization->isArchived()) {
      throw OrganizationArchivedException::cannotReactivateMember();
    }

    $memberId = OrganizationMemberId::fromString($command->memberId);
    $member = $this->memberRepository->findById($memberId);

    if (null === $member || (string) $member->organizationId() !== (string) $organizationId) {
      throw OrganizationMemberNotFoundException::withId($command->memberId);
    }

    if ($member->isActive()) {
      throw OrganizationMemberNotInactiveException::withId($command->memberId);
    }

    $this->transactionManager->transactional(function () use ($command, $member): void {
      // Enforce the member cap inside the transaction so the advisory lock
      // serializes concurrent additions/reactivations (TOCTOU) — mirrors
      // AddOrganizationMemberHandler's re-add branch.
      $this->quota->assertCanAdd($command->organizationId, OrganizationQuotaResource::MEMBERS);

      $member->activate();
      $this->memberRepository->save($member);
    });

    $roleIds = $this->memberRepository->findRoleIdsForMember($memberId);

    $this->eventDispatcher->dispatch(new OrganizationMemberAddedEvent(
      organizationId: (string) $organizationId,
      memberId: (string) $memberId,
      userId: $member->userId(),
      roleIds: $roleIds,
    ));

    return new ReactivateOrganizationMemberResult(
      memberId: (string) $memberId,
      organizationId: (string) $organizationId,
      userId: $member->userId(),
      roleIds: $roleIds,
      isActive: $member->isActive(),
      joinedAt: $member->joinedAt(),
    );
  }
  // #endregion
}
