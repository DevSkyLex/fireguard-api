<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AssignInterventionTeamInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssignInterventionTeamInput
{
  /**
   * Property teamId.
   *
   * The organization team identifier whose CURRENT active members are
   * snapshot-expanded into the intervention's participants list.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Team ID is required.')]
  #[Assert\Uuid(message: 'Team ID must be a valid UUID.')]
  public string $teamId = '';
}
