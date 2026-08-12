<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\DeleteOrganizationRole\DeleteOrganizationRoleCommand;
use Organization\Domain\Exception\{OrganizationLastAdminException, OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor DeleteOrganizationRoleProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class DeleteOrganizationRoleProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * Every failure of this endpoint — including the last-administrator refusal,
   * which the handler now raises from inside its own transaction — arrives
   * wrapped in `MessengerRuntimeException`, so the clause using this trait is
   * what maps them all.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteOrganizationRoleProcessor class.
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
   * Processes the delete-role request and dispatches the corresponding command.
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
    $roleId = $uriVariables['roleId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId
      || !is_string($roleId) || '' === $roleId) {
      throw new BadRequestHttpException('OrganizationId and roleId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.roles.manage')) {
      throw new AccessDeniedHttpException('Missing organization.roles.manage permission.');
    }

    try {
      $this->commandBus->dispatch(new DeleteOrganizationRoleCommand(
        organizationId: $organizationId,
        roleId: $roleId,
      ));
    } catch (MessengerRuntimeException $exception) {
      $lastAdmin = $this->findWrappedException($exception, OrganizationLastAdminException::class);
      if (null !== $lastAdmin) {
        throw new ConflictHttpException($lastAdmin->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, OrganizationRoleNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findWrappedException($exception, InvalidArgumentException::class);
      if (null !== $invalidArgument) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    return null;
  }
  // #endregion
}
