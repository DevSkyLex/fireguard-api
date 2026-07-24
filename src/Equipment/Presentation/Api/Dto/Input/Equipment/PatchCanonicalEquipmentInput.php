<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Input\Equipment;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PatchCanonicalEquipmentInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PatchCanonicalEquipmentInput
{
  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 32)]
  public ?string $type = null;

  /**
   * Property subType.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  public ?string $subType = null;

  /**
   * Property brand.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  public ?string $brand = null;

  /**
   * Property model.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  public ?string $model = null;

  /**
   * Property serialNumber.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 100)]
  public ?string $serialNumber = null;

  /**
   * Property locationLabel.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  public ?string $locationLabel = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['in_stock', 'operational', 'decommissioned', 'under_maintenance'])]
  public ?string $status = null;

  /**
   * Property facility.
   *
   * @since 1.0.0
   */
  public ?string $facility = null;
}
