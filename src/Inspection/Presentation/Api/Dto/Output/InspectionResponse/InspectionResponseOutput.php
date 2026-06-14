<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\InspectionResponse;

/**
 * DTO InspectionResponseOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResponseOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  public string $id;

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  public string $organization;

  /**
   * Property intervention.
   *
   * @since 1.0.0
   */
  public ?string $intervention = null;

  /**
   * Property inspection.
   *
   * @since 1.0.0
   */
  public string $inspection;

  /**
   * Property recordStatus.
   *
   * @since 1.0.0
   */
  public string $recordStatus;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  public int $revision;

  /**
   * Property itemKey.
   *
   * @since 1.0.0
   */
  public string $itemKey;

  /**
   * Property value.
   *
   * @since 1.0.0
   */
  public mixed $value = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  public string $updatedAt;
}
