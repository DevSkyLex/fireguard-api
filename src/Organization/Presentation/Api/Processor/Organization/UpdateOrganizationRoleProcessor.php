<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole\{UpdateOrganizationRoleCommand, UpdateOrganizationRoleResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Input\Organization\UpdateOrganizationRoleInput;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_map;
use function is_string;

/**
 * Processor UpdateOrganizationRoleProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<UpdateOrganizationRoleInput, OrganizationRoleOutput>
 */
final readonly class UpdateOrganizationRoleProcessor implements ProcessorInterface
{
  // #region Constructor
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
   * Processes API input and dispatches the corresponding command.
   *
   * @since 1.0.0
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
      /** @var UpdateOrganizationRoleResult $result */
      $result = $this->commandBus->dispatch(new UpdateOrganizationRoleCommand(
        organizationId: $organizationId,
        roleId: $roleId,
        permissions: $data->permissions,
        description: $data->description,
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
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
