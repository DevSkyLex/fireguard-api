<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationEquipmentStatistics\{GetOrganizationEquipmentStatisticsQuery, GetOrganizationEquipmentStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationEquipmentStatisticsOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationQueryExceptions;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider GetOrganizationEquipmentStatisticsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationEquipmentStatisticsOutput>
 */
final readonly class GetOrganizationEquipmentStatisticsProvider implements ProviderInterface
{
  use UnwrapsOrganizationQueryExceptions;

  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationEquipmentStatisticsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.equipment.read')) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    try {
      /** @var GetOrganizationEquipmentStatisticsResult $result */
      $result = $this->queryBus->ask(new GetOrganizationEquipmentStatisticsQuery($organizationId, $user->getId()));
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

    $output = new OrganizationEquipmentStatisticsOutput();
    $output->totalCount = $result->totalCount;
    $output->inStockCount = $result->inStockCount;
    $output->operationalCount = $result->operationalCount;
    $output->underMaintenanceCount = $result->underMaintenanceCount;
    $output->decommissionedCount = $result->decommissionedCount;
    $output->countsByType = $result->countsByType;

    return $output;
  }
}
