<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics\{GetOrganizationStatisticsQuery, GetOrganizationStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationStatisticsOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationQueryExceptions;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider GetOrganizationStatisticsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationStatisticsOutput>
 */
final readonly class GetOrganizationStatisticsProvider implements ProviderInterface
{
  use UnwrapsOrganizationQueryExceptions;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationStatisticsProvider class.
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
   * Provides resource data for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationStatisticsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.read')) {
      throw new AccessDeniedHttpException('Missing organization.read permission.');
    }

    try {
      /** @var GetOrganizationStatisticsResult $result */
      $result = $this->queryBus->ask(new GetOrganizationStatisticsQuery($organizationId, $user->getId()));
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $accessDenied = $this->findWrappedException($exception, OrganizationAccessDeniedException::class);
      if ($accessDenied instanceof OrganizationAccessDeniedException) {
        throw new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if ($notFound instanceof OrganizationNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new OrganizationStatisticsOutput();
    $output->memberCount = $result->memberCount;
    $output->roleCount = $result->roleCount;
    $output->facilityCount = $result->facilityCount;
    $output->activeFacilityCount = $result->activeFacilityCount;
    $output->pendingInvitationCount = $result->pendingInvitationCount;

    return $output;
  }
  // #endregion
}
