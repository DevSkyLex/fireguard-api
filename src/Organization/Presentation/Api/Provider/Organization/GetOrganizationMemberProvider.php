<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationMember\{GetOrganizationMemberQuery, GetOrganizationMemberResult};
use Organization\Domain\Exception\OrganizationMemberNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider GetOrganizationMemberProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationMemberOutput>
 */
final readonly class GetOrganizationMemberProvider implements ProviderInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The query bus wraps every handler-thrown exception into
   * `MessengerRuntimeException` (see `MessengerQueryBusAdapter::ask()`), so a
   * direct `catch (OrganizationMemberNotFoundException)` around
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
   * Initializes a new instance of the GetOrganizationMemberProvider class.
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
   * Resolves a single organization member. `GetOrganizationMemberQuery`
   * resolves by member identifier alone (it is also used cross-module by
   * Intervention), so a member that exists but belongs to a different
   * organization than the one in the URL is deliberately reported as 404
   * here rather than as a cross-tenant read.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrganizationMemberOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $memberId = $uriVariables['memberId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($memberId) || '' === $memberId) {
      throw new BadRequestHttpException('OrganizationId and memberId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.read')) {
      throw new AccessDeniedHttpException('Missing organization.members.read permission.');
    }

    try {
      /** @var GetOrganizationMemberResult $result */
      $result = $this->queryBus->ask(new GetOrganizationMemberQuery($memberId));
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationMemberNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    if ($result->organizationId !== $organizationId) {
      throw new NotFoundHttpException('Organization member not found in this organization.');
    }

    $output = new OrganizationMemberOutput();
    $output->id = $result->id;
    $output->organizationId = $result->organizationId;
    $output->userId = $result->userId;
    $output->displayName = $result->userId;
    $output->isActive = $result->isActive;
    $output->joinedAt = $result->joinedAt->format('c');

    return $output;
  }
  // #endregion
}
