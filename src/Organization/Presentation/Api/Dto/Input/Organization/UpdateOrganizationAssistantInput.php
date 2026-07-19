<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\Catalog\OrganizationAssistantDefaults;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationAssistantInput.
 *
 * Partial organization AI-assistant policy payload. Every field is optional;
 * only the provided fields are applied on top of the current settings.
 *
 * The `model` field is validated for SHAPE only here (non-blank, bounded
 * length, safe character set). Validation against the operator's servable
 * models (`OLLAMA_ALLOWED_MODELS`) is added together with the Assistant module
 * itself, which is what introduces that environment variable — until then no
 * consumer reads this value. The character-set constraint matters regardless:
 * it keeps a model identifier from carrying path or URL syntax.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationAssistantInput
{
  // #region Properties
  /**
   * Property enabled.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the AI assistant is available to this organization', required: false)]
  public ?bool $enabled = null;

  /**
   * Property model.
   *
   * @since 1.0.0
   */
  #[Assert\Type('string')]
  #[Assert\Length(max: 120)]
  #[Assert\Regex(pattern: '/^[A-Za-z0-9._:-]*$/', message: 'The assistant model may only contain letters, digits, dots, underscores, colons, and hyphens.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Model override for this organization. Omit to leave unchanged; send an empty string to revert to the operator-configured default.', required: false)]
  public ?string $model = null;

  /**
   * Property temperature.
   *
   * @since 1.0.0
   */
  #[Assert\Type('numeric')]
  #[Assert\Range(min: OrganizationAssistantDefaults::MIN_TEMPERATURE, max: OrganizationAssistantDefaults::MAX_TEMPERATURE)]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Sampling temperature; lower is more deterministic', required: false)]
  public ?float $temperature = null;

  /**
   * Property includeBusinessContext.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the server pre-injects a bounded business-context block alongside the thread transcript', required: false)]
  public ?bool $includeBusinessContext = null;
  // #endregion
}
