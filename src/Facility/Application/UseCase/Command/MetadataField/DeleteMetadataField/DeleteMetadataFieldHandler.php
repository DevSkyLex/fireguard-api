<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\DeleteMetadataField;

use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Domain\Exception\FacilityMetadataFieldNotFoundException;
use Facility\Domain\ValueObject\{FacilityMetadataFieldId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase DeleteMetadataFieldHandler.
 *
 * Deletes the field definition only. Existing facility `metadata` entries
 * carrying this key are left untouched — they simply become "unschema'd"
 * free-form values again, matching the back-compat rule the whole schema
 * feature is built on (see FacilityMetadataSchemaGuard).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteMetadataFieldHandler implements CommandHandler
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
   * @param DeleteMetadataFieldCommand $command the command payload
   *
   * @return DeleteMetadataFieldResult the use case result
   */
  public function __invoke(DeleteMetadataFieldCommand $command): DeleteMetadataFieldResult
  {
    try {
      $fieldId = FacilityMetadataFieldId::fromString($command->fieldId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $field = $this->repository->findById($fieldId);
    if (null === $field || (string) $field->organizationId() !== (string) $organizationId) {
      throw FacilityMetadataFieldNotFoundException::withId($command->fieldId);
    }

    $this->repository->delete($fieldId);

    return new DeleteMetadataFieldResult(id: (string) $fieldId);
  }
  // #endregion
}
