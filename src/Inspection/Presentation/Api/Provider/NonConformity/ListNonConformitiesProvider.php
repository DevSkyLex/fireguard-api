<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\NonConformity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\{ListNonConformitiesQuery, NonConformityResult};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
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
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_numeric;
use function is_string;
use function max;

/**
 * Class ListNonConformitiesProvider
 *
 * Applies organization access, filters and pagination to the non-conformity collection response.
 *
 * @category Provider
 *
 * @implements ProviderInterface<NonConformityOutput>
 */
final readonly class ListNonConformitiesProvider implements ProviderInterface
{
  use InspectionExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies query, authorization, user and request context services for collection reads.
   *
   * @access public
   *
   * @param QueryBusPort $queryBus dispatches non-conformity queries
   * @param OrganizationAuthorizationPort $authorization checks organization read access
   * @param Security $security provides the current authenticated user
   * @param RequestStack $requestStack provides the current request for query parameters
   *
   * @return void
   */
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
   * Method provide
   *
   * Validates collection access, dispatches a paginated query and maps its results.
   *
   * @access public
   *
   * @param Operation $operation API Platform operation being provided
   * @param array<string, mixed> $uriVariables route variables, including organizationId and inspectionId
   * @param array<string, mixed> $context API Platform provider context
   *
   * @return TraversablePaginator<NonConformityOutput> paginated non-conformity response
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    $filters = \Shared\Presentation\Api\Http\OperationParameterReader::filters($operation, $context);
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;
    $query = $this->listQuery($operation, $context, $organizationId, $inspectionId, $offset, $itemsPerPage);

    try {
      /** @var PaginatedResult<NonConformityResult> $queryResult */
      $queryResult = $this->queryBus->ask($query);
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception);
      if ($notFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($queryResult->items as $nc) {
      $outputs[] = $this->mapResult($nc);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $queryResult->total,
    );
  }

  /**
   * Method listQuery
   *
   * Builds the query from route identifiers, filters, pagination, search and sorting context.
   *
   * @access private
   *
   * @param array<string, mixed> $context
   * @param Operation $operation API Platform operation that defines query parameters
   * @param string $organizationId organization that owns the inspection
   * @param string $inspectionId inspection whose non-conformities are listed
   * @param int $offset number of matching records to skip
   * @param int $itemsPerPage maximum number of matching records to request
   *
   * @return ListNonConformitiesQuery collection query with normalized criteria
   */
  private function listQuery(
    Operation $operation,
    array $context,
    string $organizationId,
    string $inspectionId,
    int $offset,
    int $itemsPerPage,
  ): ListNonConformitiesQuery {
    $params = \Shared\Presentation\Api\Http\OperationParameterReader::query($operation, $this->requestStack->getCurrentRequest());

    return new ListNonConformitiesQuery(
      organizationId: $organizationId,
      inspectionId: $inspectionId,
      severity: self::optionalString($params->get('severity')),
      status: self::optionalString($params->get('status')),
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
      search: SearchExtractor::fromContext($context),
      sorting: SortingExtractor::fromContext($context, ['severity', 'status', 'dueAt', 'createdAt'], 'createdAt'),
    );
  }

  /**
   * Method optionalString
   *
   * Converts a non-empty string filter value to a query criterion.
   *
   * @access private
   *
   * @param mixed $value filter value to normalize
   *
   * @return string|null non-empty string, or null for other values and empty strings
   */
  private static function optionalString(mixed $value): ?string
  {
    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * Method mapResult
   *
   * Copies a query result into the API output DTO.
   *
   * @access private
   *
   * @param NonConformityResult $result non-conformity query data
   *
   * @return NonConformityOutput mapped response object
   */
  private function mapResult(NonConformityResult $result): NonConformityOutput
  {
    $output = new NonConformityOutput();
    $output->id = $result->nonConformityId;
    $output->inspectionId = $result->inspectionId;
    $output->description = $result->description;
    $output->severity = $result->severity;
    $output->status = $result->status;
    $output->dueAt = $result->dueAt;
    $output->resolvedAt = $result->resolvedAt;
    $output->notes = $result->notes;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
