<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\ListInspections\ListInspectionsQuery;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Mapper\InspectionOutputMapper;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
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

use function is_numeric;
use function is_string;
use function max;

/** @implements ProviderInterface<InspectionOutput> */
final readonly class ListInspectionsProvider implements ProviderInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private QueryBusPort $queryBus,
    private InspectionOutputMapper $outputMapper,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * @return TraversablePaginator<InspectionOutput>
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

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $equipmentId = $request?->query->get('equipmentId');
    $uriFacilityId = $uriVariables['facilityId'] ?? null;
    $queryFacilityId = $request?->query->get('facilityId');
    $facilityId = is_string($uriFacilityId) && '' !== $uriFacilityId ? $uriFacilityId : $queryFacilityId;
    $result = $request?->query->get('result');
    $status = $request?->query->get('status');
    $performedAtFrom = $request?->query->get('performedAtFrom');
    $performedAtTo = $request?->query->get('performedAtTo');
    $inspectorUserId = $request?->query->get('inspectorUserId');
    $checklistId = $request?->query->get('checklistId');

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
      /** @var PaginatedResult<GetInspectionResult> $queryResult */
      $queryResult = $this->queryBus->ask(new ListInspectionsQuery(
        organizationId: $organizationId,
        equipmentId: is_string($equipmentId) && '' !== $equipmentId ? $equipmentId : null,
        facilityId: is_string($facilityId) && '' !== $facilityId ? $facilityId : null,
        result: is_string($result) && '' !== $result ? $result : null,
        status: is_string($status) && '' !== $status ? $status : null,
        performedAtFrom: is_string($performedAtFrom) && '' !== $performedAtFrom ? $performedAtFrom : null,
        performedAtTo: is_string($performedAtTo) && '' !== $performedAtTo ? $performedAtTo : null,
        inspectorUserId: is_string($inspectorUserId) && '' !== $inspectorUserId ? $inspectorUserId : null,
        checklistId: is_string($checklistId) && '' !== $checklistId ? $checklistId : null,
        pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
        search: SearchExtractor::fromContext($context),
        sorting: SortingExtractor::fromContext($context, ['result', 'status', 'performedAt', 'createdAt'], 'createdAt'),
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
    foreach ($queryResult->items as $inspection) {
      $outputs[] = $this->mapResult($inspection);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $queryResult->total,
    );
  }

  private function mapResult(GetInspectionResult $result): InspectionOutput
  {
    return $this->outputMapper->fromGetResult($result);
  }
}
