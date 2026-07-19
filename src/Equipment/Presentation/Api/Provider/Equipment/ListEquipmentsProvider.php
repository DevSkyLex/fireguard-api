<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\ListEquipmentsQuery;
use Equipment\Presentation\Api\Dto\Output\Equipment\{EquipmentOutput, TagOutput};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function array_map;
use function is_numeric;
use function is_string;
use function max;

/**
 * Provider ListEquipmentsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<EquipmentOutput>
 */
final readonly class ListEquipmentsProvider implements ProviderInterface
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @return TraversablePaginator<EquipmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.equipment.read')) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $uriFacilityId = $uriVariables['facilityId'] ?? null;
    $queryFacilityId = $request?->query->get('facilityId');
    $facilityId = is_string($uriFacilityId) && '' !== $uriFacilityId ? $uriFacilityId : $queryFacilityId;
    $type = $request?->query->get('type');
    $status = $request?->query->get('status');
    $brand = $request?->query->get('brand');
    $model = $request?->query->get('model');
    $subType = $request?->query->get('subType');
    $maintenanceDueStatus = $request?->query->get('maintenanceDueStatus');

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    try {
      /** @var PaginatedResult<GetEquipmentResult> $result */
      $result = $this->queryBus->ask(new ListEquipmentsQuery(
        organizationId: $organizationId,
        facilityId: is_string($facilityId) && '' !== $facilityId ? $facilityId : null,
        type: is_string($type) && '' !== $type ? $type : null,
        status: is_string($status) && '' !== $status ? $status : null,
        brand: is_string($brand) && '' !== $brand ? $brand : null,
        model: is_string($model) && '' !== $model ? $model : null,
        subType: is_string($subType) && '' !== $subType ? $subType : null,
        pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
        search: SearchExtractor::fromContext($context),
        sorting: SortingExtractor::fromContext($context, ['type', 'status', 'brand', 'model', 'createdAt'], 'createdAt'),
        maintenanceDueStatus: is_string($maintenanceDueStatus) && '' !== $maintenanceDueStatus ? $maintenanceDueStatus : null,
      ));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($result->items as $equipment) {
      $outputs[] = $this->mapResult($equipment);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

  /**
   * Method mapResult.
   *
   * @since 1.0.0
   */
  private function mapResult(GetEquipmentResult $result): EquipmentOutput
  {
    $output = new EquipmentOutput();
    $output->id = $result->equipmentId;
    $output->organizationId = $result->organizationId;
    $output->facilityId = $result->facilityId;
    $output->type = $result->type;
    $output->subType = $result->subType;
    $output->brand = $result->brand;
    $output->model = $result->model;
    $output->serialNumber = $result->serialNumber;
    $output->locationLabel = $result->locationLabel;
    $output->status = $result->status;
    $output->installedAt = $result->installedAt;
    $output->commissionedAt = $result->commissionedAt;
    $output->tags = array_map(TagOutput::fromArray(...), $result->tags);
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');
    $output->maintenanceDueStatus = $result->maintenanceDueStatus;

    return $output;
  }

  // #endregion
}
