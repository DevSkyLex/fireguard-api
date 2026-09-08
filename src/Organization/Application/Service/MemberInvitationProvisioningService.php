<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use InvalidArgumentException;
use Organization\Application\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionMemberInvitationResult, ProvisionOutcome};
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Organization\Application\Port\Inbound\MemberInvitationProvisioningPort;
use Organization\Application\Port\Outbound\OrganizationRoleRepositoryPort;
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationMembershipConflictException, OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleName};
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Email;
use Throwable;

use function sprintf;
use function strtolower;
use function trim;

/**
 * Service MemberInvitationProvisioningService.
 *
 * Implements {@see MemberInvitationProvisioningPort} by resolving the
 * requested organization role *names* to their identifiers (looked up inside
 * this module, via `OrganizationRoleRepositoryPort`) and then dispatching the
 * existing `InviteOrganizationMemberCommand` through the command bus — the
 * same synchronous path the HTTP API uses, so the member-cap quota
 * (`OrganizationQuotaPort::assertCanAdd()`, taken inside the use case's
 * transaction) and every conflict rule run intact. Every failure the command
 * bus can raise for this command is translated into a typed
 * {@see ProvisionMemberInvitationResult} outcome rather than rethrown,
 * unwrapping `MessengerRuntimeException` exactly like
 * `EquipmentProvisioningService` does.
 *
 * A **dry-run** request never reaches the use case at all: the email is
 * validated structurally and every role name is resolved (an empty list
 * resolves the organization's default `member` role, mirroring the use
 * case's own fallback), then `CREATED` is answered without persisting an
 * invitation or sending an email. Deliberately lighter than the
 * Equipment/Facility dry runs — no quota projection and no
 * pending-invitation/active-member lookup (see the request contract's
 * docblock and `src/Import/MODULE.md`).
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MemberInvitationProvisioningService implements MemberInvitationProvisioningPort
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constants
  /**
   * Constant DEFAULT_MEMBER_ROLE.
   *
   * The default role name resolved when a request carries no role names —
   * the same fallback `InviteOrganizationMemberHandler` applies to an empty
   * `roleIds` list.
   *
   * @since 1.0.0
   *
   * @var string DEFAULT_MEMBER_ROLE
   */
  private const string DEFAULT_MEMBER_ROLE = 'member';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus value
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationRoleRepositoryPort $roleRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provision.
   *
   * @since 1.0.0
   *
   * @param ProvisionMemberInvitationRequest $request the provisioning request
   *
   * @return ProvisionMemberInvitationResult the provisioning outcome
   */
  public function provision(ProvisionMemberInvitationRequest $request): ProvisionMemberInvitationResult
  {
    try {
      new Email(strtolower(trim($request->email)));
    } catch (InvalidValueException) {
      return new ProvisionMemberInvitationResult(
        ProvisionOutcome::INVALID,
        message: sprintf('Invalid email address "%s".', $request->email),
      );
    }

    $roleIds = [];

    try {
      $roleIds = $this->resolveRoleIds($request);
    } catch (OrganizationRoleNotFoundException $exception) {
      return new ProvisionMemberInvitationResult(ProvisionOutcome::UNKNOWN_ROLE, message: $exception->getMessage());
    }

    if ($request->dryRun) {
      // Nothing is persisted and no email is sent: structural validation
      // (email + role names) is the whole of the member dry run.
      return new ProvisionMemberInvitationResult(ProvisionOutcome::CREATED);
    }

    try {
      /** @var InviteOrganizationMemberResult $result */
      $result = $this->commandBus->dispatch(new InviteOrganizationMemberCommand(
        organizationId: $request->organizationId,
        email: $request->email,
        invitedByUserId: $request->invitedByUserId,
        roleIds: $roleIds,
      ));
    } catch (OrganizationQuotaExceededException $exception) {
      return new ProvisionMemberInvitationResult(ProvisionOutcome::QUOTA_EXCEEDED, message: $exception->getMessage());
    } catch (OrganizationMembershipConflictException $exception) {
      return $this->fromConflict($exception);
    } catch (OrganizationRoleNotFoundException $exception) {
      return new ProvisionMemberInvitationResult(ProvisionOutcome::UNKNOWN_ROLE, message: $exception->getMessage());
    } catch (OrganizationNotFoundException|InvalidValueException|InvalidArgumentException $exception) {
      return new ProvisionMemberInvitationResult(ProvisionOutcome::INVALID, message: $exception->getMessage());
    } catch (MessengerRuntimeException $exception) {
      return $this->fromWrappedException($exception);
    }

    return new ProvisionMemberInvitationResult(ProvisionOutcome::CREATED, resourceId: $result->invitationId);
  }

  /**
   * Method resolveRoleIds.
   *
   * Resolves the requested role names to identifiers within the
   * organization; an empty list resolves the default `member` role, the
   * same fallback the invitation use case applies.
   *
   * @since 1.0.0
   *
   * @param ProvisionMemberInvitationRequest $request the provisioning request
   *
   * @throws OrganizationRoleNotFoundException when a role name does not exist in the organization
   *
   * @return list<string> the resolved role identifiers
   */
  private function resolveRoleIds(ProvisionMemberInvitationRequest $request): array
  {
    $organizationId = OrganizationId::fromString($request->organizationId);

    $names = [] !== $request->roleNames ? $request->roleNames : [self::DEFAULT_MEMBER_ROLE];

    $roleIds = [];
    foreach ($names as $name) {
      try {
        $roleName = new OrganizationRoleName($name);
      } catch (Throwable) {
        throw OrganizationRoleNotFoundException::withName($name);
      }

      $role = $this->roleRepository->findByOrganizationAndName($organizationId, $roleName);
      if (null === $role) {
        throw OrganizationRoleNotFoundException::withName($name);
      }

      $roleIds[] = (string) $role->id();
    }

    return $roleIds;
  }

  /**
   * Method fromConflict.
   *
   * Maps a membership conflict to its distinct outcome — an address already
   * holding an active membership versus one already holding a pending
   * invitation — using the exception's own conflict discriminator.
   *
   * @since 1.0.0
   *
   * @param OrganizationMembershipConflictException $exception the conflict raised by the use case
   *
   * @return ProvisionMemberInvitationResult the provisioning outcome
   */
  private function fromConflict(OrganizationMembershipConflictException $exception): ProvisionMemberInvitationResult
  {
    $outcome = OrganizationMembershipConflictException::CONFLICT_PENDING_INVITATION === $exception->conflict()
      ? ProvisionOutcome::ALREADY_INVITED
      : ProvisionOutcome::ALREADY_MEMBER;

    return new ProvisionMemberInvitationResult($outcome, message: $exception->getMessage());
  }

  /**
   * Method fromWrappedException.
   *
   * Unwraps a command-bus-wrapped failure into its provisioning outcome.
   *
   * @since 1.0.0
   *
   * @param MessengerRuntimeException $exception the wrapped exception
   *
   * @return ProvisionMemberInvitationResult the provisioning outcome
   */
  private function fromWrappedException(MessengerRuntimeException $exception): ProvisionMemberInvitationResult
  {
    $quota = $this->findException($exception, OrganizationQuotaExceededException::class);
    if ($quota instanceof OrganizationQuotaExceededException) {
      return new ProvisionMemberInvitationResult(ProvisionOutcome::QUOTA_EXCEEDED, message: $quota->getMessage());
    }

    $conflict = $this->findException($exception, OrganizationMembershipConflictException::class);
    if ($conflict instanceof OrganizationMembershipConflictException) {
      return $this->fromConflict($conflict);
    }

    $role = $this->findException($exception, OrganizationRoleNotFoundException::class);
    if ($role instanceof OrganizationRoleNotFoundException) {
      return new ProvisionMemberInvitationResult(ProvisionOutcome::UNKNOWN_ROLE, message: $role->getMessage());
    }

    $invalid = $this->findException($exception, OrganizationNotFoundException::class)
      ?? $this->findException($exception, InvalidValueException::class)
      ?? $this->findException($exception, InvalidArgumentException::class);
    if (null !== $invalid) {
      return new ProvisionMemberInvitationResult(ProvisionOutcome::INVALID, message: $invalid->getMessage());
    }

    throw $exception;
  }
  // #endregion
}
