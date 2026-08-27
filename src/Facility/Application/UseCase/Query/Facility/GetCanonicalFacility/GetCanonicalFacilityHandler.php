<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetCanonicalFacility;

use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Facility\Application\Port\Outbound\CanonicalFacilityRepositoryPort;
use Facility\Domain\ValueObject\FacilityId;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetCanonicalFacilityHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCanonicalFacilityHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalFacilityRepositoryPort $facilities the canonical facility repository
   */
  public function __construct(
    private CanonicalFacilityRepositoryPort $facilities,
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
   * @param GetCanonicalFacilityQuery $query the query payload
   *
   * @return GetCanonicalFacilityResult the use case result
   */
  public function __invoke(GetCanonicalFacilityQuery $query): GetCanonicalFacilityResult
  {
    try {
      $id = FacilityId::fromString($query->facilityId);
    } catch (InvalidValueException) {
      return new GetCanonicalFacilityResult();
    }

    $facility = $this->facilities->findById($id);

    if (null === $facility) {
      return new GetCanonicalFacilityResult();
    }

    return new GetCanonicalFacilityResult(new CanonicalFacilityView(
      id: (string) $facility->id(),
      organizationId: (string) $facility->organizationId(),
      recordStatus: $facility->recordStatus()->value,
      interventionId: $facility->interventionId(),
      revision: $facility->revision(),
    ));
  }
  // #endregion
}
