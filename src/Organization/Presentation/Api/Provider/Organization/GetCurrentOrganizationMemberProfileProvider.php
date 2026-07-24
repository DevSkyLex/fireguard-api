<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile\{
  GetCurrentOrganizationMemberProfileQuery,
  GetCurrentOrganizationMemberProfileResult
};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\{
  CurrentOrganizationMemberProfileOutput,
  OrganizationPermissionOutput,
  OrganizationRoleOutput
};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function array_map;
use function is_string;

/**
 * Provider GetCurrentOrganizationMemberProfileProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CurrentOrganizationMemberProfileOutput>
 */
final readonly class GetCurrentOrganizationMemberProfileProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetCurrentOrganizationMemberProfileProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides resource data for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CurrentOrganizationMemberProfileOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    try {
      /** @var GetCurrentOrganizationMemberProfileResult $result */
      $result = $this->queryBus->ask(new GetCurrentOrganizationMemberProfileQuery($organizationId, $user->getId()));
    } catch (OrganizationNotFoundException|OrganizationMemberNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $output = new CurrentOrganizationMemberProfileOutput();
    $output->id = $result->id;
    $output->organizationId = $result->organizationId;
    $output->userId = $result->userId;
    $output->isActive = $result->isActive;
    $output->joinedAt = $result->joinedAt->format('c');
    $output->roles = array_map(
      static function ($role): OrganizationRoleOutput {
        $output = new OrganizationRoleOutput();
        $output->id = $role->id;
        $output->organizationId = $role->organizationId;
        $output->name = $role->name;
        $output->permissions = array_map(
          static function (string $permissionName): OrganizationPermissionOutput {
            $permission = new OrganizationPermissionOutput();
            $permission->name = $permissionName;
            $permission->description = OrganizationPermissionCatalog::descriptionFor($permissionName);

            return $permission;
          },
          $role->permissions,
        );
        $output->isSystem = $role->isSystem;
        $output->createdAt = $role->createdAt->format('c');
        $output->description = $role->description;
        $output->memberCount = $role->memberCount;

        return $output;
      },
      $result->roles,
    );
    $output->permissions = array_map(
      static function (string $permissionName): OrganizationPermissionOutput {
        $permission = new OrganizationPermissionOutput();
        $permission->name = $permissionName;
        $permission->description = OrganizationPermissionCatalog::descriptionFor($permissionName);

        return $permission;
      },
      $result->permissions,
    );

    return $output;
  }
  // #endregion
}
