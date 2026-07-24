<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Intervention\Presentation\Api\Validator\ValidDuration\ValidDuration;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateInterventionTemplateInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateInterventionTemplateInput
{
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 160)]
  public ?string $name = null;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 5000)]
  public ?string $description = null;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['site_setup', 'inventory', 'inspection_campaign'])]
  public ?string $type = null;

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
  public ?string $priority = null;

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
  #[ValidDuration]
  #[ApiProperty(example: 'P14D')]
  public ?string $duration = null;

  /**
   * Property labelIds.
   *
   * PATCH replaces the full set: when this field is present in the
   * merge-patch body, the template's labels become exactly this list.
   *
   * @since 1.0.0
   *
   * @var list<string>|null
   */
  #[Assert\All([new Assert\Type('string')])]
  public ?array $labelIds = null;

  /**
   * Property items.
   *
   * PATCH replaces the full set: when this field is present in the
   * merge-patch body, the template's items become exactly this list.
   *
   * @since 1.0.0
   *
   * @var list<InterventionTemplateItemInput>|null
   */
  #[Assert\Valid]
  public ?array $items = null;
}
