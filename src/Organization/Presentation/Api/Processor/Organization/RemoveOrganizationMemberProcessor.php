<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationLastAdminGuardPort};
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember\RemoveOrganizationMemberCommand;
use Organization\Domain\Exception\{OrganizationLastAdminException, OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor RemoveOrganizationMemberProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class RemoveOrganizationMemberProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveOrganizationMemberProcessor class.
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
    private OrganizationLastAdminGuardPort $lastAdminGuard,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the remove-member request and dispatches the corresponding command.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $memberId = $uriVariables['memberId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId
      || !is_string($memberId) || '' === $memberId) {
      throw new BadRequestHttpException('OrganizationId and memberId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.manage')) {
      throw new AccessDeniedHttpException('Missing organization.members.manage permission.');
    }

    try {
      $this->lastAdminGuard->assertCanRemoveMember($organizationId, $memberId);
      $this->commandBus->dispatch(new RemoveOrganizationMemberCommand(
        organizationId: $organizationId,
        memberId: $memberId,
      ));
    } catch (OrganizationLastAdminException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (OrganizationMemberNotFoundException|OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    return null;
  }
  // #endregion
}
