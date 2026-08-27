<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListCanonicalInspections;

use Inspection\Application\Contract\Inspection\CanonicalInspectionReadView;
use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\ValueObject\{InspectionOrganizationId, InspectionRecordStatus};
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase ListCanonicalInspectionsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListCanonicalInspectionsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspectionRepositoryPort $inspections the canonical inspection repository
   * @param NonConformityRepositoryPort $nonConformities the non-conformity repository
   */
  public function __construct(
    private CanonicalInspectionRepositoryPort $inspections,
    private NonConformityRepositoryPort $nonConformities,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * **The `recordStatus` default is part of the endpoint's contract**: a
   * caller scoping to an intervention is looking at the scratchpad that
   * intervention is preparing, so drafts are what they mean; anyone else is
   * looking at the compliance record, so published is. An explicit
   * `recordStatus` always wins.
   *
   * **The non-conformity counts come from ONE grouped query over the whole
   * page.** The provider used to reach `$record->nonConformities->count()`
   * per row, which is an N+1 — the same defect L1.10b fixed on the checklist
   * listing.
   *
   * @since 1.0.0
   *
   * @param ListCanonicalInspectionsQuery $query the query payload
   *
   * @return ListCanonicalInspectionsResult the page and its total
   */
  public function __invoke(ListCanonicalInspectionsQuery $query): ListCanonicalInspectionsResult
  {
    $organizationId = InspectionOrganizationId::fromString($query->organizationId);
    $recordStatus = $query->recordStatus ?? (null === $query->interventionId
      ? InspectionRecordStatus::PUBLISHED->value
      : InspectionRecordStatus::DRAFT->value);

    $total = $this->inspections->countReadByFilters(
      $organizationId,
      $query->interventionId,
      $query->equipmentId,
      $recordStatus,
    );

    $views = $this->inspections->findReadByFilters(
      $organizationId,
      $query->interventionId,
      $query->equipmentId,
      $recordStatus,
      $query->itemsPerPage,
      ($query->page - 1) * $query->itemsPerPage,
    );

    $counts = [] === $views ? [] : $this->nonConformities->countsByInspectionIds(
      array_map(static fn (CanonicalInspectionReadView $view): string => $view->id, $views),
    );

    return new ListCanonicalInspectionsResult(
      views: array_map(
        static fn (CanonicalInspectionReadView $view): CanonicalInspectionReadView => $view->withNonConformitiesCount(
          $counts[$view->id] ?? 0,
        ),
        $views,
      ),
      page: $query->page,
      itemsPerPage: $query->itemsPerPage,
      total: $total,
    );
  }
  // #endregion
}
