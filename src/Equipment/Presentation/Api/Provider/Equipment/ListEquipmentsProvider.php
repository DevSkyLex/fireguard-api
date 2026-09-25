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
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Factory\EquipmentOutputFactory;
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
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

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
    private EquipmentOutputFactory $outputFactory,
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

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $uriFacilityId = $uriVariables['facilityId'] ?? null;
    $queryFacilityId = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('facilityId');
    $facilityId = self::optionalFilter($uriFacilityId) ?? self::optionalFilter($queryFacilityId);
    $type = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('type');
    $status = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('status');
    $brand = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('brand');
    $model = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('model');
    $subType = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('subType');
    $maintenanceDueStatus = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('maintenanceDueStatus');

    $filters = \Shared\Presentation\Api\Http\OperationParameterReader::filters($operation, $context);
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
        facilityId: $facilityId,
        type: self::optionalFilter($type),
        status: self::optionalFilter($status),
        brand: self::optionalFilter($brand),
        model: self::optionalFilter($model),
        subType: self::optionalFilter($subType),
        pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
        search: SearchExtractor::fromContext($context),
        sorting: SortingExtractor::fromContext($context, ['type', 'status', 'brand', 'model', 'createdAt', 'updatedAt'], 'createdAt'),
        maintenanceDueStatus: self::optionalFilter($maintenanceDueStatus),
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
      $outputs[] = $this->outputFactory->fromView($equipment);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

  private static function optionalFilter(mixed $value): ?string
  {
    return is_string($value) && '' !== $value ? $value : null;
  }

  // #endregion
}
