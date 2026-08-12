<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationLastAdminGuardPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole\{UpdateOrganizationRoleCommand, UpdateOrganizationRoleResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationLastAdminException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Input\Organization\UpdateOrganizationRoleInput;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;

use function array_map;
use function is_string;

/**
 * Processor UpdateOrganizationRoleProcessor.
 *
 * @category Processor
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateOrganizationRoleInput, OrganizationRoleOutput>
 */
final readonly class UpdateOrganizationRoleProcessor implements ProcessorInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The bus adapters wrap every handler failure into
   * `MessengerRuntimeException`, so the direct `catch` clauses only cover a
   * bare in-process throw. The `MessengerRuntimeException` clauses using
   * this trait are what map the real dispatch path.
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
   * UpdateOrganizationRoleProcessor class.
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
    private OrganizationLastAdminGuardPort $lastAdminGuard,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method process.
   *
   * Processes API input and dispatches the corresponding command. Symfony
   * Messenger's `HandleMessageMiddleware` always wraps a handler-thrown
   * exception in `HandlerFailedException` before
   * `MessengerCommandBusAdapter::dispatch()` wraps THAT in
   * `MessengerRuntimeException` — so the direct `catch` clauses below cover
   * only the guard ports, which run in-process before dispatch; the
   * `MessengerRuntimeException` clause is what maps the real runtime path.
   *
   * @since 1.1.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationRoleOutput
  {
    /** @var UpdateOrganizationRoleInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $roleId = $uriVariables['roleId'] ?? null;
    if (!is_string($roleId) || '' === $roleId) {
      throw new BadRequestHttpException('RoleId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.roles.manage')) {
      throw new AccessDeniedHttpException('Missing organization.roles.manage permission.');
    }

    try {
      $this->grantGuard->assertCanGrantPermissions($user->getId(), $organizationId, $data->permissions);
      $this->lastAdminGuard->assertCanUpdateRolePermissions($organizationId, $roleId, $data->permissions);

      /** @var UpdateOrganizationRoleResult $result */
      $result = $this->commandBus->dispatch(new UpdateOrganizationRoleCommand(
        organizationId: $organizationId,
        roleId: $roleId,
        permissions: $data->permissions,
        name: $data->name,
        description: $data->description,
      ));
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (OrganizationLastAdminException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $accessDenied = $this->findWrappedException($exception, OrganizationAccessDeniedException::class);
      if (null !== $accessDenied) {
        throw new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
      }

      $conflict = $this->findWrappedException($exception, OrganizationLastAdminException::class);
      if (null !== $conflict) {
        throw new ConflictHttpException($conflict->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findWrappedException($exception, InvalidArgumentException::class);
      if (null !== $invalidArgument) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new OrganizationRoleOutput();
    $output->id = $result->id;
    $output->organizationId = $result->organizationId;
    $output->name = $result->name;
    $output->permissions = array_map(
      static function (string $name): OrganizationPermissionOutput {
        $perm = new OrganizationPermissionOutput();
        $perm->name = $name;
        $perm->description = OrganizationPermissionCatalog::descriptionFor($name);

        return $perm;
      },
      $result->permissions,
    );
    $output->isSystem = $result->isSystem;
    $output->createdAt = $result->createdAt->format('c');
    $output->description = $result->description;

    return $output;
  }

  // #endregion
}
