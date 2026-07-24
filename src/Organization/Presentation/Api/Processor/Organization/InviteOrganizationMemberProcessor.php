<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException, OrganizationQuotaExceededException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Dto\Input\Organization\InviteOrganizationMemberInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Organization\Presentation\Api\Trait\InvitationOutputMapperTrait;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor InviteOrganizationMemberProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<InviteOrganizationMemberInput, OrganizationInvitationOutput>
 */
final readonly class InviteOrganizationMemberProcessor implements ProcessorInterface
{
  use InvitationOutputMapperTrait;
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * InviteOrganizationMemberProcessor class.
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
    private OrganizationPermissionGrantGuardPort $grantGuard,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes API input and dispatches the corresponding command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationInvitationOutput
  {
    /** @var InviteOrganizationMemberInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing Organization.members.manage permission.');
    }

    try {
      // Privilege-escalation guard: reject inviting with roles that carry
      // permissions the inviter does not hold (these role ids are replayed
      // verbatim when the invitation is accepted).
      $this->grantGuard->assertCanAssignRoles($user->getId(), $organizationId, $data->roleIds);

      /** @var InviteOrganizationMemberResult $result */
      $result = $this->commandBus->dispatch(new InviteOrganizationMemberCommand(
        organizationId: $organizationId,
        email: $data->email,
        invitedByUserId: $user->getId(),
        roleIds: $data->roleIds,
      ));
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException|OrganizationRoleNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      // The member cap is now enforced inside the handler transaction, so its
      // 409 arrives wrapped by the bus and must be unwrapped here.
      $quotaExceeded = $this->findException($exception, OrganizationQuotaExceededException::class);
      if ($quotaExceeded instanceof OrganizationQuotaExceededException) {
        throw new ConflictHttpException($quotaExceeded->getMessage(), $exception);
      }

      throw $exception;
    }

    return $this->buildInvitationOutput($result);
  }
  // #endregion
}
