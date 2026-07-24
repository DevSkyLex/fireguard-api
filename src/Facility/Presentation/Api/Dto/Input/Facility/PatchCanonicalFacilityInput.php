<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Dto\Input\Facility;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PatchCanonicalFacilityInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PatchCanonicalFacilityInput
{
  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['site', 'building', 'floor', 'zone', 'area'])]
  public ?string $type = null;

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 120)]
  public ?string $name = null;

  /**
   * Property code.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 80)]
  public ?string $code = null;

  /**
   * Property address.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  public ?string $address = null;

  /**
   * Property latitude.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: -90, max: 90)]
  public ?float $latitude = null;

  /**
   * Property longitude.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: -180, max: 180)]
  public ?float $longitude = null;

  /**
   * Property metadata.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>|null
   */
  public ?array $metadata = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['active', 'archived'])]
  public ?string $status = null;

  /**
   * Property parent.
   *
   * @since 1.0.0
   */
  public ?string $parent = null;
}
