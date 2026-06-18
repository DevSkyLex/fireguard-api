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
   * Property targetSummary.
   *
   * Read-only target summary (kind + label) resolved at read time from
   * {@see $target}. Null when there is no target, the target is free text, or
   * the linked resource can no longer be resolved.
   *
   * @since 1.1.0
   */
  public ?InterventionTargetOutput $targetSummary = null;

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
   * Property assigneeProfile.
   *
   * Read-only assignee identity (display name + avatar) resolved at read time
   * from {@see $assignee}. Null when the work item is unassigned or the member
   * can no longer be resolved.
   *
   * @since 1.1.0
   */
  public ?InterventionAssigneeOutput $assigneeProfile = null;

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
