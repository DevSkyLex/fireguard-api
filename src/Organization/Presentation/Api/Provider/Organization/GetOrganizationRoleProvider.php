<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationRole\{GetOrganizationRoleQuery, GetOrganizationRoleResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_map;
use function is_string;

/**
 * Provider GetOrganizationRoleProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationRoleOutput>
 */
final readonly class GetOrganizationRoleProvider implements ProviderInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The query bus wraps every handler-thrown exception into
   * `MessengerRuntimeException` (see `MessengerQueryBusAdapter::ask()`), so a
   * direct `catch (OrganizationRoleNotFoundException)` around
   * `queryBus->ask()` alone would never match at runtime — this trait walks
   * the wrapped exception's `getPrevious()`/`HandlerFailedException` chain to
   * find the real domain exception underneath.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationRoleProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Resolves a single organization-scoped role, including its active member
   * count. `GetOrganizationRoleHandler` scopes the role lookup to the
   * organization itself — a role that exists but belongs to a different
   * organization than the URL surfaces as `OrganizationRoleNotFoundException`
   * (mapped to 404 here), never as a cross-tenant read.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrganizationRoleOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $roleId = $uriVariables['roleId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($roleId) || '' === $roleId) {
      throw new BadRequestHttpException('OrganizationId and roleId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.roles.read')) {
      throw new AccessDeniedHttpException('Missing organization.roles.read permission.');
    }

    try {
      /** @var GetOrganizationRoleResult $result */
      $result = $this->queryBus->ask(new GetOrganizationRoleQuery($organizationId, $roleId));
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationRoleNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
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
    $output->memberCount = $result->memberCount;

    return $output;
  }
  // #endregion
}
