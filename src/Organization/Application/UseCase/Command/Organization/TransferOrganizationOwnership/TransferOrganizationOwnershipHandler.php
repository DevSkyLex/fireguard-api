<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationPermissionGrantGuardPort;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\Event\Organization\OrganizationOwnershipTransferredEvent;
use Organization\Domain\Event\Role\OrganizationRoleAssignedEvent;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationDeletionConfirmationMismatchException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationRoleName};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort};
use Throwable;

use function in_array;
use function strtolower;
use function trim;

/**
 * UseCase TransferOrganizationOwnershipHandler.
 *
 * Transfers ownership of an organization from its current owner to another
 * active member. Ownership transfer is intentionally gated on the current
 * owner identity rather than on RBAC permissions: no permission grants the
 * right to give away someone else's ownership.
 *
 * The acting user's own membership is resolved (and the owner check applied)
 * BEFORE the danger-zone slug confirmation is validated. This closes an
 * existence/slug oracle: a stranger who is not an active member is refused
 * with the same 404 an unknown organization id would produce, before ever
 * reaching the slug comparison — they can neither confirm the organization
 * exists nor validate slug guesses against it. An active member who is not
 * the owner still receives 403: they already legitimately know the
 * organization exists.
 *
 * Because the organization's owner is always expected to hold the system
 * "admin" role (see CreateOrganizationHandler, which grants it at creation),
 * the new owner is granted that role on transfer if they do not already hold
 * it. That grant is itself guarded by OrganizationPermissionGrantGuardPort
 * (the same no-privilege-escalation check every other role-granting surface
 * enforces: an actor may only grant permissions it already holds) and is
 * entirely best-effort: a missing "admin" system role, a guard refusal, or
 * any unexpected failure while granting it is logged and skipped rather than
 * failing an ownership transfer that has already been durably committed.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TransferOrganizationOwnershipHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * TransferOrganizationOwnershipHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   * @param OrganizationPermissionGrantGuardPort $grantGuard the no-privilege-escalation role-grant guard port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher port
   * @param LoggerPort $logger the logger port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationPermissionGrantGuardPort $grantGuard,
    private EventDispatcherPort $eventDispatcher,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param TransferOrganizationOwnershipCommand $command the command payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the acting user is not
   *                                             an active member of the
   *                                             organization, or when the
   *                                             target user is not an active
   *                                             member of the organization
   * @throws OrganizationAccessDeniedException when the acting user is an
   *                                           active member but not the
   *                                           organization's current owner
   * @throws OrganizationDeletionConfirmationMismatchException when the slug
   *                                                           confirmation is
   *                                                           missing or does
   *                                                           not match the
   *                                                           organization's
   *                                                           current slug
   *
   * @return TransferOrganizationOwnershipResult the use case result
   */
  public function __invoke(TransferOrganizationOwnershipCommand $command): TransferOrganizationOwnershipResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    // Resolve the acting user's own membership BEFORE the slug confirmation
    // is validated. A caller who is not an active member is refused with the
    // same 404 a nonexistent organization id would produce, so a stranger can
    // neither confirm the organization exists nor use the slug-mismatch
    // response to validate guesses.
    $actingMember = $this->memberRepository->findByOrganizationAndUser($organizationId, $command->actingUserId);

    if (null === $actingMember || !$actingMember->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($command->actingUserId, $command->organizationId);
    }

    // Only the current owner may transfer ownership, regardless of RBAC
    // permissions: this is an ownership decision, not a permission grant. An
    // active member who is not the owner already legitimately knows the
    // organization exists, so 403 (rather than 404) is safe here.
    if ($command->actingUserId !== $organization->ownerUserId()) {
      throw OrganizationAccessDeniedException::ownershipTransferRequiresCurrentOwner();
    }

    // Danger-zone guard: the caller must type the organization's current slug
    // to confirm intent, exactly as DeleteOrganization requires. Comparison
    // is normalized (trim + lowercase) without constructing the value object,
    // so arbitrary typed text is a mismatch rather than a validation error.
    $expectedSlug = (string) $organization->slug();
    $providedSlug = null !== $command->slugConfirmation ? strtolower(trim($command->slugConfirmation)) : '';

    if ('' === $providedSlug) {
      throw OrganizationDeletionConfirmationMismatchException::missing();
    }

    if ($providedSlug !== $expectedSlug) {
      throw OrganizationDeletionConfirmationMismatchException::mismatched();
    }

    $newOwnerMember = $this->memberRepository->findByOrganizationAndUser($organizationId, $command->newOwnerUserId);

    if (null === $newOwnerMember || !$newOwnerMember->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($command->newOwnerUserId, $command->organizationId);
    }

    $previousOwnerUserId = $command->actingUserId;

    // Invariants (archived guard, same-owner guard) enforced inside the aggregate.
    $organization->transferOwnership($command->newOwnerUserId);
    $this->organizationRepository->save($organization);

    $this->eventDispatcher->dispatch(new OrganizationOwnershipTransferredEvent(
      organizationId: (string) $organizationId,
      previousOwnerUserId: $previousOwnerUserId,
      newOwnerUserId: $command->newOwnerUserId,
    ));

    $this->grantAdminRoleIfMissing(
      organizationId: $organizationId,
      newOwnerMemberId: $newOwnerMember->id(),
      organizationIdString: $command->organizationId,
      actingUserId: $command->actingUserId,
      newOwnerUserId: $command->newOwnerUserId,
    );

    return new TransferOrganizationOwnershipResult(
      organizationId: (string) $organizationId,
      previousOwnerUserId: $previousOwnerUserId,
      newOwnerUserId: $command->newOwnerUserId,
      transferredAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method grantAdminRoleIfMissing.
   *
   * Ensures the new owner holds the organization's system "admin" role,
   * mirroring the owner-gets-admin invariant applied at organization
   * creation (see CreateOrganizationHandler). Entirely best-effort: the
   * ownership transfer has already been durably saved and its event
   * dispatched by the time this runs, so nothing here may fail the use case.
   * A missing "admin" system role, a guard refusal (the acting user does not
   * hold every permission the role carries — the same no-privilege-escalation
   * check AssignOrganizationRoleToMemberProcessor and AddOrganizationMemberProcessor
   * apply), or any other failure while granting it is logged and skipped.
   *
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationMemberId $newOwnerMemberId the new owner's member identifier
   * @param string $organizationIdString the organization identifier as a string, for logging and the guard call
   * @param string $actingUserId the user identifier performing the transfer (the previous owner), whose permissions gate the grant
   * @param string $newOwnerUserId the new owner's user identifier, for logging
   */
  private function grantAdminRoleIfMissing(
    OrganizationId $organizationId,
    OrganizationMemberId $newOwnerMemberId,
    string $organizationIdString,
    string $actingUserId,
    string $newOwnerUserId,
  ): void {
    try {
      $adminRole = $this->roleRepository->findByOrganizationAndName(
        $organizationId,
        new OrganizationRoleName(OrganizationSystemRoleCatalog::ADMIN),
      );

      if (null === $adminRole) {
        $this->logger->warning('Organization ownership transferred but no "admin" system role exists to grant.', [
          'organizationId' => $organizationIdString,
          'newOwnerUserId' => $newOwnerUserId,
        ]);

        return;
      }

      $currentRoleIds = $this->memberRepository->findRoleIdsForMember($newOwnerMemberId);

      if (in_array((string) $adminRole->id(), $currentRoleIds, true)) {
        return;
      }

      // No-privilege-escalation guard: the acting user (previous owner) must
      // hold every permission the "admin" role carries before it can grant
      // that role to the new owner.
      $this->grantGuard->assertCanAssignRoles($actingUserId, $organizationIdString, [(string) $adminRole->id()]);

      $this->memberRepository->assignRole($newOwnerMemberId, $adminRole->id());

      $this->eventDispatcher->dispatch(new OrganizationRoleAssignedEvent(
        organizationId: $organizationIdString,
        memberId: (string) $newOwnerMemberId,
        roleId: (string) $adminRole->id(),
        roleName: (string) $adminRole->name(),
      ));
    } catch (Throwable $exception) {
      $this->logger->warning('Organization ownership transferred but granting the "admin" system role to the new owner was skipped.', [
        'organizationId' => $organizationIdString,
        'newOwnerUserId' => $newOwnerUserId,
        'actingUserId' => $actingUserId,
        'error' => $exception->getMessage(),
      ]);
    }
  }
  // #endregion
}
