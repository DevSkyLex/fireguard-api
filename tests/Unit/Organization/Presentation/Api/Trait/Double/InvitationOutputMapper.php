<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Trait\Double;

use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\InviteOrganizationMemberResult;
use Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation\ResendOrganizationInvitationResult;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Organization\Presentation\Api\Trait\InvitationOutputMapperTrait;

/**
 * Test double exposing InvitationOutputMapperTrait.
 *
 * @category Test Double
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvitationOutputMapper
{
  use InvitationOutputMapperTrait;

  /**
   * Maps an invitation command result onto its output DTO.
   */
  public function map(
    InviteOrganizationMemberResult|ResendOrganizationInvitationResult $result,
  ): OrganizationInvitationOutput {
    return $this->buildInvitationOutput($result);
  }
}
