<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary\{GetFacilityEquipmentSummaryQuery, GetFacilityEquipmentSummaryResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\FacilityEquipmentSummaryOutput;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider FacilityEquipmentSummaryProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityEquipmentSummaryOutput>
 */
final readonly class FacilityEquipmentSummaryProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queries dispatches the equipment-owned summary read
   * @param OrganizationAuthorizationPort $authorization enforces equipment read access
   * @param Security $security resolves the authenticated caller through its public identity contract
   * @param RequestStack $requests reads the optional descendant scope
   */
  public function __construct(private QueryBusPort $queries, private OrganizationAuthorizationPort $authorization, private Security $security, private RequestStack $requests)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the summary endpoint metadata
   * @param array<string, mixed> $uriVariables the organization and facility identifiers
   * @param array<string, mixed> $context the API Platform provider context
   *
   * @return FacilityEquipmentSummaryOutput the complete summary representation
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FacilityEquipmentSummaryOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    $facilityId = $uriVariables['facilityId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('OrganizationId and facilityId URI parameters are required.');
    }
    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }
    $includeDescendants = $this->requests->getCurrentRequest()?->query->getBoolean('includeDescendants', true) ?? true;
    /** @var GetFacilityEquipmentSummaryResult $result */
    $result = $this->queries->ask(new GetFacilityEquipmentSummaryQuery($organizationId, $facilityId, $includeDescendants));
    $output = new FacilityEquipmentSummaryOutput();
    $output->scope = $result->scope;
    $output->totalItems = $result->totalItems;
    $output->byStatus = $result->byStatus;
    $output->needingAttentionCount = $result->needingAttentionCount;

    return $output;
  }
  // #endregion
}
