<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\UpdateMetadataField;

use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Domain\Exception\FacilityMetadataFieldNotFoundException;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId,
  FacilityType
};
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * UseCase UpdateMetadataFieldHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateMetadataFieldHandler implements CommandHandler
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
   * @param UpdateMetadataFieldCommand $command the command payload
   *
   * @return UpdateMetadataFieldResult the use case result
   */
  public function __invoke(UpdateMetadataFieldCommand $command): UpdateMetadataFieldResult
  {
    try {
      $fieldId = FacilityMetadataFieldId::fromString($command->fieldId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException|ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $field = $this->repository->findById($fieldId);
    if (null === $field || (string) $field->organizationId() !== (string) $organizationId) {
      throw FacilityMetadataFieldNotFoundException::withId($command->fieldId);
    }

    try {
      if ($command->hasLabel) {
        if (null === $command->label) {
          throw InvalidValueException::because('Field "label" cannot be null when provided.');
        }

        $field->rename(new FacilityMetadataFieldLabel($command->label));
      }

      if ($command->hasFieldType) {
        if (null === $command->fieldType) {
          throw InvalidValueException::because('Field "fieldType" cannot be null when provided.');
        }

        $field->changeType(
          FacilityMetadataFieldType::from($command->fieldType),
          $command->hasOptions ? ($command->options ?? []) : $field->options(),
        );
      } elseif ($command->hasOptions) {
        $field->changeOptions($command->options ?? []);
      }

      if ($command->hasRequired) {
        $field->changeRequired($command->required ?? false);
      }

      if ($command->hasFacilityType) {
        $field->changeFacilityType(null === $command->facilityType ? null : FacilityType::from($command->facilityType));
      }

      if ($command->hasUnit) {
        $field->changeUnit($command->unit);
      }
    } catch (InvalidValueException|ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $this->repository->save($field);

    return new UpdateMetadataFieldResult(
      id: (string) $field->id(),
      organizationId: (string) $field->organizationId(),
      key: (string) $field->key(),
      label: (string) $field->label(),
      fieldType: $field->fieldType()->value,
      required: $field->required(),
      options: $field->options(),
      facilityType: $field->facilityType()?->value,
      unit: $field->unit(),
      createdAt: $field->createdAt(),
      updatedAt: $field->updatedAt(),
    );
  }
  // #endregion
}
