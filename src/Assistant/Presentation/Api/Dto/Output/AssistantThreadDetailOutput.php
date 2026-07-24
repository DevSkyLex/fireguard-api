<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AssistantThreadDetailOutput.
 *
 * A single assistant thread together with a page of its messages, oldest
 * first (chat order).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantThreadDetailOutput
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

  /**
   * Property messages.
   *
   * @since 1.0.0
   *
   * @var list<AssistantMessageOutput>
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $messages = [];

  /**
   * Property messagesPage.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $messagesPage = 1;

  /**
   * Property messagesItemsPerPage.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $messagesItemsPerPage = 50;

  /**
   * Property messagesTotal.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $messagesTotal = 0;
  // #endregion
}
