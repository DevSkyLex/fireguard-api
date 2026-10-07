<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Replacement;

/**
 * Class EquipmentReplacementSuccessor
 *
 * Creation fields for a new asset; owner, placement and publication belong to the replacement.
 *
 * @category Contract
 */
final readonly class EquipmentReplacementSuccessor
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $type the catalog equipment type
   * @param ?string $subType the declared subtype
   * @param ?string $brand the manufacturer
   * @param ?string $model the model
   * @param ?string $serialNumber the serial identity
   * @param ?string $locationLabel the declared location
   * @param ?string $name the display name
   * @param ?string $assetCode the patrimonial identity
   * @param ?string $criticality the declared criticality
   * @param list<array{key: string, value: string, unit?: ?string}> $technicalProperties declarative technical characteristics
   *
   * @return void
   */
  public function __construct(
    public string $type,
    public ?string $subType = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $serialNumber = null,
    public ?string $locationLabel = null,
    public ?string $name = null,
    public ?string $assetCode = null,
    public ?string $criticality = null,
    public array $technicalProperties = [],
  ) {
  }
  // #endregion
}
