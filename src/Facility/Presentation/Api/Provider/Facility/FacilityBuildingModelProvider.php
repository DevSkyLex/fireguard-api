<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel\{GetFacilityBuildingModelQuery, GetFacilityBuildingModelResult};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityBuildingModelOutput;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider FacilityBuildingModelProvider.
 *
 * Handles `GET /organizations/{organizationId}/facilities/{facilityId}/building-model`.
 *
 * Every business decision — the building/not-building check, the outline
 * cascade, the geometric-leaf filtering — lives in
 * `GetFacilityBuildingModelHandler`. This provider only resolves the
 * organization-scoped access gate, dispatches the query, and maps the
 * Result to the Output DTO. `FacilityNotFoundException`,
 * `FacilityNotBuildingException`, and `InvalidValueException` are already
 * mapped centrally (`config/packages/api_platform.yaml`), so no exception is
 * caught here.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityBuildingModelOutput>
 */
final readonly class FacilityBuildingModelProvider implements ProviderInterface
{
  // #region Constructor
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
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FacilityBuildingModelOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $facilityId = $uriVariables['facilityId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('OrganizationId and facilityId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    /** @var GetFacilityBuildingModelResult $result */
    $result = $this->queryBus->ask(new GetFacilityBuildingModelQuery(
      organizationId: $organizationId,
      facilityId: $facilityId,
    ));

    $output = new FacilityBuildingModelOutput();
    $output->buildingId = $result->buildingId;
    $output->buildingName = $result->buildingName;
    $output->floors = $result->floors;

    return $output;
  }
  // #endregion
}
