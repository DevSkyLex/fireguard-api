<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateInterventionInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateInterventionInput
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
   * Property type.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['site_setup', 'inventory', 'inspection_campaign'])]
  public string $type = 'site_setup';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 160)]
  public string $name = '';

  /**
   * Property referencePack.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $referencePack = '/api/reference-packs/fr-erp-ert-v1';

  /**
   * Property site.
   *
   * @since 1.0.0
   */
  public ?string $site = null;

  /**
   * Property responsible.
   *
   * @since 1.0.0
   */
  public ?string $responsible = null;

  /**
   * Property participants.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Assert\All([new Assert\Type('string')])]
  public array $participants = [];

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
  public string $priority = 'normal';

  /**
   * Property plannedStartAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime]
  public ?string $plannedStartAt = null;

  /**
   * Property dueAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime]
  public ?string $dueAt = null;
}
