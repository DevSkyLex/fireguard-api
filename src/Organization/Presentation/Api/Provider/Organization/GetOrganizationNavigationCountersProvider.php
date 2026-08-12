<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Organization\GetNavigationCounters\{GetNavigationCountersQuery, GetNavigationCountersResult};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationNavigationCountersOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider GetOrganizationNavigationCountersProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationNavigationCountersOutput>
 */
final readonly class GetOrganizationNavigationCountersProvider implements ProviderInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The query bus wraps every handler-thrown exception into
   * `MessengerRuntimeException` (see `MessengerQueryBusAdapter::ask()`), so
   * a direct `catch (OrganizationNotFoundException|OrganizationMemberNotFoundException)`
   * around `queryBus->ask()` alone would never match at runtime — this trait
   * (already used by `GetOrganizationDashboardProvider`) walks the wrapped
   * exception's `getPrevious()`/`HandlerFailedException` chain to find the
   * real domain exception underneath.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
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
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationNavigationCountersOutput
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
      /** @var GetNavigationCountersResult $result */
      $result = $this->queryBus->ask(new GetNavigationCountersQuery($organizationId, $user->getId()));
    } catch (OrganizationNotFoundException|OrganizationMemberNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class)
        ?? $this->findWrappedException($exception, OrganizationMemberNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new OrganizationNavigationCountersOutput();
    $output->openInterventions = $result->openInterventions;
    $output->openNonConformities = $result->openNonConformities;

    return $output;
  }
  // #endregion
}
