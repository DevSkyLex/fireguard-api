<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection;

use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\ValueObject\InspectionId;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase ReadCanonicalInspectionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReadCanonicalInspectionHandler implements QueryHandler
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
   * The non-conformity count comes from the grouped query, not from a lazy
   * association — the same call the listing uses, so the item and the list
   * can never report a different number for the same row.
   *
   * A malformed identifier answers "not found" rather than "invalid": before
   * the identifier was a value object, `$entityManager->find()` returned null
   * for any unparseable string and the endpoint answered 404.
   *
   * @since 1.0.0
   *
   * @param ReadCanonicalInspectionQuery $query the query payload
   *
   * @return ReadCanonicalInspectionResult the inspection when it exists
   */
  public function __invoke(ReadCanonicalInspectionQuery $query): ReadCanonicalInspectionResult
  {
    try {
      $id = InspectionId::fromString($query->inspectionId);
    } catch (InvalidValueException) {
      return new ReadCanonicalInspectionResult();
    }

    $view = $this->inspections->findReadById($id);

    if (null === $view) {
      return new ReadCanonicalInspectionResult();
    }

    $counts = $this->nonConformities->countsByInspectionIds([$view->id]);

    return new ReadCanonicalInspectionResult($view->withNonConformitiesCount($counts[$view->id] ?? 0));
  }
  // #endregion
}
