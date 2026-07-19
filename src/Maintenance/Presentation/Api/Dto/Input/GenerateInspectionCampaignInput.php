<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO GenerateInspectionCampaignInput.
 *
 * `POST /api/maintenance/campaigns` request body.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GenerateInspectionCampaignInput
{
  /**
   * Property organization.
   *
   * The organization IRI (or bare identifier).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $organization = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 160)]
  public string $name = '';

  /**
   * Property facility.
   *
   * Optional facility IRI (or bare identifier) filter.
   *
   * @since 1.0.0
   */
  public ?string $facility = null;

  /**
   * Property equipmentType.
   *
   * Optional equipment type filter.
   *
   * @since 1.0.0
   */
  public ?string $equipmentType = null;

  /**
   * Property dueBefore.
   *
   * ISO-8601 upper bound on the next due date.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $dueBefore = '';
}
