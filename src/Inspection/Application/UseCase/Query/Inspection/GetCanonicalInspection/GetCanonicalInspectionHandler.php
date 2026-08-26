<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection;

use Inspection\Application\Contract\Inspection\CanonicalInspectionView;
use Inspection\Application\Port\Outbound\CanonicalInspectionRepositoryPort;
use Inspection\Domain\ValueObject\InspectionId;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetCanonicalInspectionHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCanonicalInspectionHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalInspectionRepositoryPort $inspections the canonical inspection repository
   */
  public function __construct(
    private CanonicalInspectionRepositoryPort $inspections,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * A malformed identifier answers "not found" rather than "invalid": before
   * the identifier was a value object, `$entityManager->find()` simply
   * returned null for any unparseable string and the endpoint answered 404.
   *
   * @since 1.0.0
   *
   * @param GetCanonicalInspectionQuery $query the query payload
   *
   * @return GetCanonicalInspectionResult the use case result
   */
  public function __invoke(GetCanonicalInspectionQuery $query): GetCanonicalInspectionResult
  {
    try {
      $id = InspectionId::fromString($query->inspectionId);
    } catch (InvalidValueException) {
      return new GetCanonicalInspectionResult();
    }

    $inspection = $this->inspections->findById($id);

    if (null === $inspection) {
      return new GetCanonicalInspectionResult();
    }

    return new GetCanonicalInspectionResult(new CanonicalInspectionView(
      id: (string) $inspection->id(),
      organizationId: (string) $inspection->organizationId(),
      recordStatus: $inspection->recordStatus()->value,
      interventionId: $inspection->interventionId(),
      revision: $inspection->revision(),
    ));
  }
  // #endregion
}
