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
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\OperationParameterReader;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_numeric;
use function is_string;
use function max;

/**
 * Class ListInspectionsProvider.
 *
 * Translates authorized inspection collection requests into queries and paginated outputs.
 *
 * @category Provider
 *
 * @implements ProviderInterface<InspectionOutput>
 */
final readonly class ListInspectionsProvider implements ProviderInterface
{
  use InspectionExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListInspectionsProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param InspectionOutputFactory $outputMapper the output mapper value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private InspectionOutputFactory $outputMapper,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Authorizes the requested collection before dispatching its filtered query.
   *
   * @access public
   *
   * @param Operation $operation the collection operation
   * @param array<string, mixed> $uriVariables the organization and optional facility route scope
   * @param array<string, mixed> $context the parsed pagination, search and sorting context
   *
   * @return TraversablePaginator<InspectionOutput> the inspection collection page
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->authenticatedUser();
    $organizationId = self::organizationId($uriVariables);
    $this->assertReadAccess($user, $organizationId);
    $bounds = self::pageBounds($operation, $context);
    $query = $this->listQuery(
      $operation,
      $uriVariables,
      $context,
      $organizationId,
      ($bounds['page'] - 1) * $bounds['itemsPerPage'],
      $bounds['itemsPerPage'],
    );

    return $this->paginator($this->queryResult($query), $bounds['page'], $bounds['itemsPerPage']);
  }

  /**
   * Method authenticatedUser.
   *
   * Requires the security principal expected by organization authorization.
   *
   * @access private
   *
   * @return SecurityUser the authenticated caller
   */
  private function authenticatedUser(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }

  /**
   * Method organizationId.
   *
   * Requires the organization scope supplied by the collection route.
   *
   * @access private
   *
   * @param array<string, mixed> $uriVariables the collection route variables
   *
   * @return string the organization identifier
   */
  private static function organizationId(array $uriVariables): string
  {
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    return $organizationId;
  }

  /**
   * Method hasParkScope.
   *
   * Preserves the raw request filters that trigger the additional equipment permission.
   *
   * @access private
   *
   * @return bool whether the request explicitly selects a parc scope
   */
  private function hasParkScope(): bool
  {
    $requestQuery = $this->requestStack->getCurrentRequest()?->query;

    return null !== self::optionalString($requestQuery?->get('family'))
      || null !== self::optionalString($requestQuery?->get('customerId'))
      || ($requestQuery?->getBoolean('includeDescendants', false) ?? false);
  }

  /**
   * Method assertReadAccess.
   *
   * Keeps legacy direct reads and explicit parc reads on their existing authorization paths.
   *
   * @access private
   *
   * @param SecurityUser $user the authenticated caller
   * @param string $organizationId the requested organization scope
   *
   * @return void
   */
  private function assertReadAccess(SecurityUser $user, string $organizationId): void
  {
    if (!$this->hasParkScope()) {
      if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
        throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
      }

      return;
    }

    foreach (['organization.inspection.read', 'organization.equipment.read'] as $permission) {
      $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, $permission);
      if ($decision->isOutsideScope()) {
        throw new NotFoundHttpException('Organization not found.');
      }
      if (!$decision->isGranted()) {
        throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
      }
    }
  }

  /**
   * Method pageBounds.
   *
   * Retains numeric pagination coercion, defaults and the minimum of one.
   *
   * @access private
   *
   * @param Operation $operation the collection operation with parsed parameters
   * @param array<string, mixed> $context the legacy filter context
   *
   * @return array{page: int, itemsPerPage: int} the transport pagination bounds
   */
  private static function pageBounds(Operation $operation, array $context): array
  {
    $filters = OperationParameterReader::filters($operation, $context);
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    return ['page' => max(1, $page), 'itemsPerPage' => max(1, $itemsPerPage)];
  }

  /**
   * Method queryResult.
   *
   * Preserves HTTP validation mapping and the original cause for wrapped query failures.
   *
   * @access private
   *
   * @param ListInspectionsQuery $query the authorized collection query
   *
   * @return PaginatedResult<GetInspectionResult> the filtered inspection page
   */
  private function queryResult(ListInspectionsQuery $query): PaginatedResult
  {
    try {
      /** @var PaginatedResult<GetInspectionResult> */
      return $this->queryBus->ask($query);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }
  }

  /**
   * Method paginator.
   *
   * Maps the query page to the existing inspection output and Hydra pagination shape.
   *
   * @access private
   *
   * @param PaginatedResult<GetInspectionResult> $queryResult the inspection page
   * @param int $page the current transport page
   * @param int $itemsPerPage the requested transport page size
   *
   * @return TraversablePaginator<InspectionOutput> the mapped inspection page
   */
  private function paginator(PaginatedResult $queryResult, int $page, int $itemsPerPage): TraversablePaginator
  {
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

  /**
   * Method listQuery.
   *
   * Translates parsed filters while preserving facility route precedence.
   *
   * @access private
   *
   * @param Operation $operation the collection operation
   * @param array<string, mixed> $uriVariables the collection route scope
   * @param array<string, mixed> $context the search and sorting context
   * @param string $organizationId the authorized organization scope
   * @param int $offset the zero-based page offset
   * @param int $itemsPerPage the page size
   *
   * @return ListInspectionsQuery the filtered inspection query
   */
  private function listQuery(
    Operation $operation,
    array $uriVariables,
    array $context,
    string $organizationId,
    int $offset,
    int $itemsPerPage,
  ): ListInspectionsQuery {
    $params = OperationParameterReader::query($operation, $this->requestStack->getCurrentRequest());
    $facilityId = self::optionalString($uriVariables['facilityId'] ?? null) ?? $params->get('facilityId');

    return new ListInspectionsQuery(
      organizationId: $organizationId,
      equipmentId: self::optionalString($params->get('equipmentId')),
      facilityId: self::optionalString($facilityId),
      result: self::optionalString($params->get('result')),
      status: self::optionalString($params->get('status')),
      performedAtFrom: self::optionalString($params->get('performedAtFrom')),
      performedAtTo: self::optionalString($params->get('performedAtTo')),
      inspectorUserId: self::optionalString($params->get('inspectorUserId')),
      checklistId: self::optionalString($params->get('checklistId')),
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
      search: SearchExtractor::fromContext($context),
      sorting: SortingExtractor::fromContext($context, ['result', 'status', 'performedAt', 'createdAt'], 'createdAt'),
      family: self::optionalString($params->get('family')),
      customerId: self::optionalString($params->get('customerId')),
      includeDescendants: $params->getBoolean('includeDescendants', false),
    );
  }

  /**
   * Method optionalString.
   *
   * Returns a non-empty string query value, or null for other values.
   *
   * @access private
   *
   * @static
   *
   * @param mixed $value the query parameter value
   *
   * @return string|null the non-empty string value
   */
  private static function optionalString(mixed $value): ?string
  {
    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * Method mapResult.
   *
   * Maps one inspection query result to its API output DTO.
   *
   * @access private
   * @since 1.0.0
   *
   * @param GetInspectionResult $result the result value
   *
   * @return InspectionOutput the map result result
   */
  private function mapResult(GetInspectionResult $result): InspectionOutput
  {
    return $this->outputMapper->fromGetResult($result);
  }
  // #endregion
}
