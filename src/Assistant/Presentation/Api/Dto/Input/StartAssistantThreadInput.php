<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Assistant\Presentation\Api\Serialization\AssistantSerializationGroup;
use Assistant\Presentation\Api\Validator\ValidAssistantModel\ValidAssistantModel;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO StartAssistantThreadInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StartAssistantThreadInput
{
  // #region Properties
  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  #[Groups([AssistantSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional display title for the thread', required: false)]
  public ?string $title = null;

  /**
   * Property model.
   *
   * The ONLY tenant-controlled model input in this module — validated
   * against the operator-curated `OLLAMA_ALLOWED_MODELS` allowlist. Never
   * influences the Ollama backend URL (`OLLAMA_BASE_URL`), which is
   * operator-only deployment configuration.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 255)]
  #[ValidAssistantModel]
  #[Groups([AssistantSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Optional tenant-selected model override, validated against the operator-configured allowlist', required: false)]
  public ?string $model = null;
  // #endregion
}
