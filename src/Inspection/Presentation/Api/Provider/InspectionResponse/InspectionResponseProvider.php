<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\InspectionResponse;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\UseCase\Query\Response\GetInspectionResponse\{GetInspectionResponseQuery, GetInspectionResponseResult};
use Inspection\Application\UseCase\Query\Response\ListInspectionResponses\{ListInspectionResponsesQuery, ListInspectionResponsesResult};
use Inspection\Application\UseCase\Query\Response\ResolveInspectionResponseScope\{ResolveInspectionResponseScopeQuery, ResolveInspectionResponseScopeResult};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_map;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Provider InspectionResponseProvider.
 *
 * Translates the two `/inspection-responses` reads into queries and maps the
 * views onto `InspectionResponseOutput`. It holds no entity manager and
 * writes no DQL: the filters, the `recordStatus` default and the paging live
 * in `Application\UseCase\Query\Response\`.
 *
 * **Two things deliberately stay here.**
 *
 * The **authorization gate**, because it must run between resolving the
 * organization and reading any row — reading a page for a caller who may not
 * see it and discarding it afterwards is both wasteful and the wrong shape.
 * `OUTSIDE_SCOPE` answers 404 and `MISSING_PERMISSION` answers 403; that
 * split is the enumeration oracle closed on 2026-08-26.
 *
 * **The pagination clamp**, because `page` and `itemsPerPage` come from API
 * Platform's own filter context and the bounds mirror the `paginationMaximumItemsPerPage`
 * and `paginationItemsPerPage` declared on the resource. A non-numeric value
 * falls back to the default rather than failing: that is what the endpoint
 * has always done.
 *
 * @category Provider
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InspectionResponseOutput>
 */
final readonly class InspectionResponseProvider implements ProviderInterface
{
  // #region Constants
  /**
   * The resource's declared page size and ceiling.
   *
   * @since 2.0.0
   */
  private const int DEFAULT_ITEMS_PER_PAGE = 50;

  private const int MAX_ITEMS_PER_PAGE = 100;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionResponseProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
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
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return InspectionResponseOutput|TraversablePaginator<InspectionResponseOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $id = $uriVariables['id'] ?? null;

    if (is_string($id)) {
      return self::output($this->item($id));
    }

    $request = $this->requestStack->getCurrentRequest();
    $interventionId = $this->filterId($request?->query->get('intervention'), 'interventions');
    $inspectionId = $this->filterId($request?->query->get('inspection'), 'inspections');
    $organizationId = $this->filterId($request?->query->get('organization'), 'organizations');

    /** @var ResolveInspectionResponseScopeResult $scope */
    $scope = $this->queryBus->ask(new ResolveInspectionResponseScopeQuery(
      organizationId: $organizationId,
      interventionId: $interventionId,
      inspectionId: $inspectionId,
    ));

    if (null === $scope->organizationId) {
      throw new BadRequestHttpException('The organization, intervention or inspection filter is required.');
    }

    $this->assertRead($scope->organizationId);

    $recordStatus = $request?->query->get('recordStatus');
    $filters = $context['filters'] ?? [];

    /** @var ListInspectionResponsesResult $result */
    $result = $this->queryBus->ask(new ListInspectionResponsesQuery(
      organizationId: $scope->organizationId,
      interventionId: $interventionId,
      inspectionId: $inspectionId,
      recordStatus: is_string($recordStatus) && '' !== $recordStatus ? $recordStatus : null,
      page: self::page($filters),
      itemsPerPage: self::itemsPerPage($filters),
    ));

    return new TraversablePaginator(
      new ArrayIterator(array_map(self::output(...), $result->views)),
      (float) $result->page,
      (float) $result->itemsPerPage,
      (float) $result->total,
    );
  }

  /**
   * Method item.
   *
   * Reads one response and gates on the organization it carries. Unknown and
   * malformed identifiers answer alike, and so does a response in an
   * organization the caller is not in — a 403 there would confirm the id
   * exists in another tenant.
   *
   * @since 1.0.0
   *
   * @param string $id the response identifier
   *
   * @return InspectionResponseView the response
   */
  private function item(string $id): InspectionResponseView
  {
    /** @var GetInspectionResponseResult $result */
    $result = $this->queryBus->ask(new GetInspectionResponseQuery($id));

    if (null === $result->view) {
      throw new NotFoundHttpException('Inspection response not found.');
    }

    $this->assertRead($result->view->organizationId);

    return $result->view;
  }

  /**
   * Method filterId.
   *
   * Parses one optional IRI filter. An absent or empty value is simply not a
   * filter.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw query value
   * @param string $resource the resource segment the IRI must carry
   *
   * @return ?string the parsed identifier, or null when the filter is absent
   */
  private function filterId(mixed $value, string $resource): ?string
  {
    return is_string($value) && '' !== $value ? ResourceIriParser::id($value, $resource) : null;
  }

  /**
   * Method assertRead.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   */
  private function assertRead(string $organizationId): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.inspection.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }
  }

  /**
   * Method page.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param mixed $filters API Platform's filter context
   *
   * @return int the 1-based page number
   */
  private static function page(mixed $filters): int
  {
    return is_array($filters) && is_numeric($filters['page'] ?? null) ? max(1, (int) $filters['page']) : 1;
  }

  /**
   * Method itemsPerPage.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param mixed $filters API Platform's filter context
   *
   * @return int the clamped page size
   */
  private static function itemsPerPage(mixed $filters): int
  {
    if (!is_array($filters) || !is_numeric($filters['itemsPerPage'] ?? null)) {
      return self::DEFAULT_ITEMS_PER_PAGE;
    }

    return max(1, min(self::MAX_ITEMS_PER_PAGE, (int) $filters['itemsPerPage']));
  }

  /**
   * Method output.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param InspectionResponseView $view the response view
   *
   * @return InspectionResponseOutput the output result
   */
  private static function output(InspectionResponseView $view): InspectionResponseOutput
  {
    $output = new InspectionResponseOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->intervention = null !== $view->interventionId ? '/api/interventions/' . $view->interventionId : null;
    $output->inspection = '/api/inspections/' . $view->inspectionId;
    $output->recordStatus = $view->recordStatus;
    $output->revision = $view->revision;
    $output->itemKey = $view->itemKey;
    $output->value = $view->value;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
