<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\InviteOrganizationMemberResult;
use Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation\ResendOrganizationInvitationResult;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;

/**
 * Trait InvitationOutputMapperTrait.
 *
 * Maps a freshly-issued invitation use-case result (invite or resend) to the
 * API OrganizationInvitationOutput, so the invite and resend processors stop
 * duplicating the identical field-by-field assignment.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait InvitationOutputMapperTrait
{
  /**
   * Method buildInvitationOutput.
   *
   * Builds the API output DTO from an invite/resend result. Both results expose
   * the same field set (a freshly issued invitation plus its accept URL).
   *
   * @since 1.0.0
   *
   * @param InviteOrganizationMemberResult|ResendOrganizationInvitationResult $result the invitation result
   *
   * @return OrganizationInvitationOutput the API output DTO
   */
  private function buildInvitationOutput(
    InviteOrganizationMemberResult|ResendOrganizationInvitationResult $result,
  ): OrganizationInvitationOutput {
    $output = new OrganizationInvitationOutput();
    $output->id = $result->invitationId;
    $output->organizationId = $result->organizationId;
    $output->email = $result->email;
    $output->status = $result->status;
    $output->invitedByUserId = $result->invitedByUserId;
    $output->expiresAt = $result->expiresAt->format('c');
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');
    $output->roleIds = $result->roleIds;
    $output->acceptUrl = $result->acceptUrl;

    return $output;
  }
}
