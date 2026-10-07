<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary\GetFacilityEquipmentSummaryResult;
use Equipment\Application\UseCase\Query\Equipment\GetOrganizationEquipmentSummary\GetOrganizationEquipmentSummaryQuery;
use Equipment\Presentation\Api\Dto\Output\Equipment\FacilityEquipmentSummaryOutput;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Organization totals with collection-compatible family and customer scopes.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityEquipmentSummaryOutput>
 */
final readonly class OrganizationEquipmentSummaryProvider implements ProviderInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private QueryBusPort $queries, private OrganizationAuthorizationPort $authorization, private Security $security, private RequestStack $requests)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables route identifiers
   * @param array<string, mixed> $context provider context
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FacilityEquipmentSummaryOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId is required.');
    }
    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }
    $parameters = $this->requests->getCurrentRequest()?->query;
    $family = $parameters?->get('family');
    $customer = $parameters?->get('customerId');
    /** @var GetFacilityEquipmentSummaryResult $result */
    $result = $this->queries->ask(new GetOrganizationEquipmentSummaryQuery($organizationId, is_string($family) && '' !== $family ? $family : null, is_string($customer) && '' !== $customer ? $customer : null));
    $output = new FacilityEquipmentSummaryOutput();
    $output->scope = $result->scope;
    $output->totalItems = $result->totalItems;
    $output->byStatus = $result->byStatus;
    $output->needingAttentionCount = $result->needingAttentionCount;

    return $output;
  }
}
