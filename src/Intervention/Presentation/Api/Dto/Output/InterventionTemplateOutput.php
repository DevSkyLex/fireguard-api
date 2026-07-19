<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionTemplateOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionTemplateOutput
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
   * Property name.
   *
   * @since 1.0.0
   */
  public string $name = '';

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  public ?string $description = null;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  public string $type = '';

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  public string $priority = '';

  /**
   * Property defaultSite.
   *
   * @since 1.0.0
   */
  public ?string $defaultSite = null;

  /**
   * Property defaultResponsible.
   *
   * @since 1.0.0
   */
  public ?string $defaultResponsible = null;

  /**
   * Property duration.
   *
   * @since 1.0.0
   */
  public ?string $duration = null;

  /**
   * Property labelIds.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $labelIds = [];

  /**
   * Property items.
   *
   * @since 1.0.0
   *
   * @var list<InterventionTemplateItemOutput>
   */
  public array $items = [];

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
