<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AssistantThreadOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantThreadOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $memberId = '';

  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $title = null;

  /**
   * Property model.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $model = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';

  /**
   * Property lastMessageAt.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastMessageAt = null;
  // #endregion
}
