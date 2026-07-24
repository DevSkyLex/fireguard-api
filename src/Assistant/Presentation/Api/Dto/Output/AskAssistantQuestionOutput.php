<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO AskAssistantQuestionOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AskAssistantQuestionOutput
{
  // #region Properties
  /**
   * Property threadId.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
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
   * Property userMessage.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public AssistantMessageOutput $userMessage;

  /**
   * Property assistantMessage.
   *
   * @since 1.0.0
   */
  #[Groups([AssistantSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public AssistantMessageOutput $assistantMessage;
  // #endregion
}
