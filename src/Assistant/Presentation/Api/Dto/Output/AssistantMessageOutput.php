<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AssistantMessageOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantMessageOutput
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
   * Property threadId.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $threadId = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property role.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $role = '';

  /**
   * Property body.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $body = '';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = '';

  /**
   * Property errorCode.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $errorCode = null;

  /**
   * Property tokenCount.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $tokenCount = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $completedAt = null;
  // #endregion
}
