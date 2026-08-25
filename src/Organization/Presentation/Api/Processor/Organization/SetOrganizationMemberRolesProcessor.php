<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\SetOrganizationMemberRoles\{SetOrganizationMemberRolesCommand, SetOrganizationMemberRolesResult};
use Organization\Domain\Exception\{
  OrganizationNotFoundException
};
use Organization\Presentation\Api\Dto\Input\Organization\SetOrganizationMemberRolesInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Processor SetOrganizationMemberRolesProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<SetOrganizationMemberRolesInput, OrganizationMemberOutput>
 */
final readonly class SetOrganizationMemberRolesProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The command bus wraps every handler-thrown exception into
   * `MessengerRuntimeException` (see `MessengerCommandBusAdapter::dispatch()`),
   * so a direct `catch (OrganizationNotFoundException|…)` around
   * `commandBus->dispatch()` alone would never match at runtime — this trait
   * walks the wrapped exception's `getPrevious()`/`HandlerFailedException`
   * chain to find the real domain exception underneath. See
   * `DeleteOrganizationProcessor` and `GetOrganizationNavigationCountersProvider`
   * for the same pattern — NOT `RemoveOrganizationMemberProcessor`'s direct
   * catches, which never fire.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * SetOrganizationMemberRolesProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the bulk role-replacement request and dispatches the
   * corresponding command. The privilege-escalation guard (roles being
   * granted) and the last-administrator lockout guard (roles being revoked)
   * both run inside the handler, exactly as they do for the unit
   * assign/remove-role operations.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationMemberOutput
  {
    /** @var SetOrganizationMemberRolesInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $memberId = $uriVariables['memberId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($memberId) || '' === $memberId) {
      throw new BadRequestHttpException('OrganizationId and memberId URI parameters are required.');
    }

    // Mirrors the unit assign/remove-role operations' permission
    // (AssignOrganizationRoleToMemberProcessor / RemoveOrganizationRoleFromMemberProcessor).
    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.roles.manage')) {
      throw new AccessDeniedHttpException('Missing organization.roles.manage permission.');
    }

    // Five exceptions, three statuses — 404 for anything unknown (including an
    // unknown role id, as AssignOrganizationRoleToMemberProcessor does), 403
    // for the privilege-escalation guard, 409 for the last-administrator
    // lockout. All five are declared, so the mapping is by class now.
    /** @var SetOrganizationMemberRolesResult $result */
    $result = $this->commandBus->dispatch(new SetOrganizationMemberRolesCommand(
      organizationId: $organizationId,
      actingUserId: $user->getId(),
      memberId: $memberId,
      roleIds: $data->roleIds,
    ));

    $output = new OrganizationMemberOutput();
    $output->id = $result->memberId;
    $output->organizationId = $result->organizationId;
    $output->userId = $result->userId;
    $output->isActive = $result->isActive;
    $output->joinedAt = $result->joinedAt->format('c');
    $output->roleIds = $result->roleIds;

    return $output;
  }
  // #endregion
}
