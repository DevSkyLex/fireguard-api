<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\MetadataField\CreateMetadataField;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Domain\Exception\{FacilityMetadataFieldKeyAlreadyExistsException, FacilityMetadataFieldLimitExceededException};
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId,
  FacilityType
};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use ValueError;

/**
 * UseCase CreateMetadataFieldHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateMetadataFieldHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant MAX_FIELDS_PER_ORGANIZATION.
   *
   * @var int
   */
  public const int MAX_FIELDS_PER_ORGANIZATION = 50;
  // #endregion

  // #region Constructor
  public function __construct(
    private FacilityMetadataFieldRepositoryPort $repository,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateMetadataFieldCommand $command the command payload
   *
   * @return CreateMetadataFieldResult the use case result
   */
  public function __invoke(CreateMetadataFieldCommand $command): CreateMetadataFieldResult
  {
    try {
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
      $key = new FacilityMetadataFieldKey($command->key);
      $label = new FacilityMetadataFieldLabel($command->label);
      $fieldType = FacilityMetadataFieldType::from($command->fieldType);
      $facilityType = null === $command->facilityType ? null : FacilityType::from($command->facilityType);

      /** @var FacilityMetadataFieldId $fieldId */
      $fieldId = $this->uuidFactory->create(FacilityMetadataFieldId::class);

      $field = FacilityMetadataField::create(
        id: $fieldId,
        organizationId: $organizationId,
        key: $key,
        label: $label,
        fieldType: $fieldType,
        required: $command->required,
        options: $command->options,
        facilityType: $facilityType,
        unit: $command->unit,
      );
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    if ($this->repository->countByOrganizationId($organizationId) >= self::MAX_FIELDS_PER_ORGANIZATION) {
      throw FacilityMetadataFieldLimitExceededException::withOrganizationId(
        (string) $organizationId,
        self::MAX_FIELDS_PER_ORGANIZATION,
      );
    }

    if (null !== $this->repository->findByOrganizationIdAndKey($organizationId, (string) $key)) {
      throw FacilityMetadataFieldKeyAlreadyExistsException::withKey((string) $key);
    }

    try {
      $this->repository->save($field);
    } catch (Throwable $exception) {
      if ($exception instanceof UniqueConstraintViolationException) {
        throw FacilityMetadataFieldKeyAlreadyExistsException::withKey((string) $key);
      }

      throw $exception;
    }

    return new CreateMetadataFieldResult(
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
