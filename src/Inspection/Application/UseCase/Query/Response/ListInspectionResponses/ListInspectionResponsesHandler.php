<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\ListInspectionResponses;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\Port\Outbound\InspectionResponseRepositoryPort;
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, InspectionResponseStatus};
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase ListInspectionResponsesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionResponsesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseRepositoryPort $responses the inspection response repository
   */
  public function __construct(
    private InspectionResponseRepositoryPort $responses,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * **The `recordStatus` default is part of the endpoint's contract**, not a
   * convenience: a caller scoping to an intervention is looking at what a
   * field client is preparing, so drafts are what they mean; anyone else is
   * looking at the compliance record, so published is. An explicit
   * `recordStatus` always wins.
   *
   * @since 1.0.0
   *
   * @param ListInspectionResponsesQuery $query the query payload
   *
   * @return ListInspectionResponsesResult the page and its total
   */
  public function __invoke(ListInspectionResponsesQuery $query): ListInspectionResponsesResult
  {
    $organizationId = InspectionOrganizationId::fromString($query->organizationId);
    $recordStatus = $query->recordStatus ?? (null === $query->interventionId
      ? InspectionResponseStatus::PUBLISHED->value
      : InspectionResponseStatus::DRAFT->value);

    $total = $this->responses->countByFilters(
      $organizationId,
      $query->interventionId,
      $query->inspectionId,
      $recordStatus,
    );

    $responses = $this->responses->findByFilters(
      $organizationId,
      $query->interventionId,
      $query->inspectionId,
      $recordStatus,
      $query->itemsPerPage,
      ($query->page - 1) * $query->itemsPerPage,
    );

    return new ListInspectionResponsesResult(
      views: array_map(
        static fn (InspectionResponse $response): InspectionResponseView => InspectionResponseView::fromModel($response),
        $responses,
      ),
      page: $query->page,
      itemsPerPage: $query->itemsPerPage,
      total: $total,
    );
  }
  // #endregion
}
