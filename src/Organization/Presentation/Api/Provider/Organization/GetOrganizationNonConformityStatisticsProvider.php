<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationNonConformityStatistics\{GetOrganizationNonConformityStatisticsQuery, GetOrganizationNonConformityStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationNonConformityStatisticsOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationQueryExceptions;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider GetOrganizationNonConformityStatisticsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationNonConformityStatisticsOutput>
 */
final readonly class GetOrganizationNonConformityStatisticsProvider implements ProviderInterface
{
  use UnwrapsOrganizationQueryExceptions;

  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationNonConformityStatisticsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    try {
      /** @var GetOrganizationNonConformityStatisticsResult $result */
      $result = $this->queryBus->ask(new GetOrganizationNonConformityStatisticsQuery($organizationId, $user->getId()));
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

    $output = new OrganizationNonConformityStatisticsOutput();
    $output->totalCount = $result->totalCount;
    $output->openCount = $result->openCount;
    $output->inProgressCount = $result->inProgressCount;
    $output->doneCount = $result->doneCount;
    $output->waivedCount = $result->waivedCount;
    $output->lowSeverityCount = $result->lowSeverityCount;
    $output->mediumSeverityCount = $result->mediumSeverityCount;
    $output->highSeverityCount = $result->highSeverityCount;
    $output->criticalSeverityCount = $result->criticalSeverityCount;

    return $output;
  }
}
