<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionWorkItemOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionWorkItemOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  public string $intervention = '';

  /**
   * Property action.
   *
   * @since 1.0.0
   */
  public string $action = '';

  /**
   * Property target.
   *
   * @since 1.0.0
   */
  public ?string $target = null;

  /**
   * Property resultResource.
   *
   * @since 1.0.0
   */
  public ?string $resultResource = null;

  /**
   * Property assignee.
   *
   * @since 1.0.0
   */
  public ?string $assignee = null;

  /**
   * Property source.
   *
   * @since 1.0.0
   */
  public string $source = 'planned';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  public string $status = 'planned';

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  public bool $required = true;

  /**
   * Property skipReason.
   *
   * @since 1.0.0
   */
  public ?string $skipReason = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  public int $revision = 1;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  public string $updatedAt = '';
}
