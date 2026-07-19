<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Intervention\Presentation\Api\Validator\ValidDuration\ValidDuration;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInterventionTemplateInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInterventionTemplateInput
{
  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[ApiProperty(example: '/api/organizations/550e8400-e29b-41d4-a716-446655440000')]
  public string $organization = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(min: 2, max: 160)]
  public string $name = '';

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
  public string $type = 'site_setup';

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
  public string $priority = 'normal';

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
   * ISO-8601 duration string (e.g. `P14D`), used to derive `dueAt` from
   * `plannedStartAt` at instantiation.
   *
   * @since 1.0.0
   */
  #[ValidDuration]
  #[ApiProperty(example: 'P14D')]
  public ?string $duration = null;

  /**
   * Property labelIds.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\All([new Assert\Type('string')])]
  public array $labelIds = [];

  /**
   * Property items.
   *
   * @since 1.0.0
   *
   * @var list<InterventionTemplateItemInput>
   */
  #[Assert\Valid]
  public array $items = [];
}
