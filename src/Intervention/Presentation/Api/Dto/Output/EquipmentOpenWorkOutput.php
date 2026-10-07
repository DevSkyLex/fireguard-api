<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

/**
 * Class EquipmentOpenWorkOutput
 *
 * Exposes navigation links to authorized existing equipment work.
 *
 * @category DTO
 */
final class EquipmentOpenWorkOutput
{
  /**
   * Property interventionId.
   */
  public string $interventionId = '';

  /**
   * Property number.
   */
  public int $number = 0;

  /**
   * Property name.
   */
  public string $name = '';

  /**
   * Property status.
   */
  public string $status = '';

  /**
   * Property workItemId.
   */
  public string $workItemId = '';

  /**
   * Property action.
   */
  public string $action = '';

  /**
   * Property workItemStatus.
   */
  public string $workItemStatus = '';
}
