<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO GenerateInspectionCampaignOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GenerateInspectionCampaignOutput
{
  /**
   * Property interventionId.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $interventionId = '';

  /**
   * Property number.
   *
   * The per-organization sequential intervention number.
   *
   * @since 1.0.0
   */
  public int $number = 0;

  /**
   * Property workItemsCount.
   *
   * @since 1.0.0
   */
  public int $workItemsCount = 0;
}
