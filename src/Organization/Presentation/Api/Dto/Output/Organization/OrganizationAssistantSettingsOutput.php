<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Domain\Catalog\OrganizationAssistantDefaults;
use Organization\Domain\ValueObject\OrganizationAssistantSettings;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationAssistantSettingsOutput.
 *
 * Read representation of an organization's AI-assistant policy.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAssistantSettingsOutput
{
  // #region Properties
  /**
   * Property enabled.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Whether the AI assistant is available to this organization')]
  public bool $enabled = OrganizationAssistantDefaults::ENABLED;

  /**
   * Property model.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Model override for this organization, or null when the operator-configured default applies')]
  public ?string $model = OrganizationAssistantDefaults::MODEL;

  /**
   * Property temperature.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Sampling temperature; lower is more deterministic')]
  public float $temperature = OrganizationAssistantDefaults::TEMPERATURE;

  /**
   * Property includeBusinessContext.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, description: 'Whether the server pre-injects a bounded business-context block alongside the thread transcript')]
  public bool $includeBusinessContext = OrganizationAssistantDefaults::INCLUDE_BUSINESS_CONTEXT;
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * Builds the output from the domain assistant settings value object.
   *
   * @since 1.0.0
   *
   * @param OrganizationAssistantSettings $assistant the domain assistant settings
   *
   * @return self the output instance
   */
  public static function fromDomain(OrganizationAssistantSettings $assistant): self
  {
    $output = new self();
    $output->enabled = $assistant->enabled;
    $output->model = $assistant->model;
    $output->temperature = $assistant->temperature;
    $output->includeBusinessContext = $assistant->includeBusinessContext;

    return $output;
  }
  // #endregion
}
