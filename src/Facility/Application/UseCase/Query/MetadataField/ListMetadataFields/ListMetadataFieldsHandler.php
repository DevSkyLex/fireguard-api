<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\MetadataField\ListMetadataFields;

use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\FacilityOrganizationId;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase ListMetadataFieldsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMetadataFieldsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private FacilityMetadataFieldRepositoryPort $repository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListMetadataFieldsQuery $query the query payload
   *
   * @return ListMetadataFieldsResult the use case result
   */
  public function __invoke(ListMetadataFieldsQuery $query): ListMetadataFieldsResult
  {
    $organizationId = FacilityOrganizationId::fromString($query->organizationId);

    $fields = $this->repository->findByOrganizationId($organizationId);

    return new ListMetadataFieldsResult(
      items: array_map(
        static fn (FacilityMetadataField $field): MetadataFieldResult => new MetadataFieldResult(
          id: (string) $field->id(),
          organizationId: (string) $field->organizationId(),
          key: (string) $field->key(),
          label: (string) $field->label(),
          fieldType: $field->fieldType()->value,
          required: $field->required(),
          options: $field->options(),
          facilityType: $field->facilityType()?->value,
          unit: $field->unit(),
        ),
        $fields,
      ),
    );
  }
  // #endregion
}
