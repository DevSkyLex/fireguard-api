<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AskAssistantQuestionInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AskAssistantQuestionInput
{
  // #region Properties
  /**
   * Property body.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 8000)]
  #[Groups([AssistantSerializationGroup::WRITE])]
  #[ApiProperty(description: 'The question body')]
  public string $body = '';

  /**
   * Property temperature.
   *
   * Optional tenant-selected sampling temperature for this generation only
   * (never persisted). One of only two tenant-controlled generation inputs
   * in this module, alongside the thread's own `model`.
   *
   * @since 1.0.0
   */
  #[Assert\Range(min: 0.0, max: 2.0)]
  #[Groups([AssistantSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional sampling temperature for this question only (0.0-2.0)', required: false)]
  public ?float $temperature = null;
  // #endregion
}
