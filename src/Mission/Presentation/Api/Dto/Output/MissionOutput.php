<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MissionOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  public string $organization = '';

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  public string $type = 'site_setup';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  public string $name = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  public string $status = 'draft';

  /**
   * Property referencePack.
   *
   * @since 1.0.0
   */
  public string $referencePack = '';

  /**
   * Property site.
   *
   * @since 1.0.0
   */
  public ?string $site = null;

  /**
   * Property responsible.
   *
   * @since 1.0.0
   */
  public ?string $responsible = null;

  /**
   * Property participants.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $participants = [];

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  public string $priority = 'normal';

  /**
   * Property plannedStartAt.
   *
   * @since 1.0.0
   */
  public ?string $plannedStartAt = null;

  /**
   * Property dueAt.
   *
   * @since 1.0.0
   */
  public ?string $dueAt = null;

  /**
   * Property reviewNote.
   *
   * @since 1.0.0
   */
  public ?string $reviewNote = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  public int $revision = 1;

  /**
   * Property facilitiesCount.
   *
   * @since 1.0.0
   */
  public int $facilitiesCount = 0;

  /**
   * Property equipmentCount.
   *
   * @since 1.0.0
   */
  public int $equipmentCount = 0;

  /**
   * Property inspectionsCount.
   *
   * @since 1.0.0
   */
  public int $inspectionsCount = 0;

  /**
   * Property blockersCount.
   *
   * @since 1.0.0
   */
  public int $blockersCount = 0;

  /**
   * Property workItemsCount.
   *
   * @since 1.0.0
   */
  public int $workItemsCount = 0;

  /**
   * Property completedWorkItemsCount.
   *
   * @since 1.0.0
   */
  public int $completedWorkItemsCount = 0;

  /**
   * Property proposedChangesCount.
   *
   * @since 1.0.0
   */
  public int $proposedChangesCount = 0;

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
