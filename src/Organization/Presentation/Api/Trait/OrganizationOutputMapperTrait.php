<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Trait;

use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationMembershipRoleOutput, OrganizationOutput, OrganizationSettingsOutput};

/**
 * Trait OrganizationOutputMapperTrait.
 *
 * Maps a `GetOrganizationResult` to the API `OrganizationOutput`, so
 * `GetOrganizationProvider` and every mutation processor that re-reads
 * through `GetOrganizationQuery` after dispatching its command
 * (suspend/restore/transfer-ownership/settings-update) stop duplicating the
 * identical field-by-field assignment — `isOwner`/`roles` included, so a
 * caller-membership fix made once here is shared by every one of them.
 *
 * @category Trait
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait OrganizationOutputMapperTrait
{
  /**
   * Method buildOrganizationOutput.
   *
   * Builds the API output DTO from a `GetOrganizationResult`. `isOwner`/
   * `roles` are copied through as-is: null when the query that produced the
   * result did not carry a `callerUserId` (unresolved caller membership),
   * a concrete `bool`/list otherwise.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationResult $result the organization query result
   *
   * @return OrganizationOutput the API output DTO
   */
  private function buildOrganizationOutput(GetOrganizationResult $result): OrganizationOutput
  {
    $output = new OrganizationOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->slug = $result->slug;
    $output->ownerUserId = $result->ownerUserId;
    $output->createdByUserId = $result->createdByUserId;
    $output->status = $result->status;
    $output->isActive = $result->isActive;
    $output->description = $result->description;
    $output->logoUrl = $result->logoUrl;
    $output->memberCount = $result->memberCount;
    $output->settings = OrganizationSettingsOutput::fromDomain($result->settings ?? OrganizationSettings::default());
    $output->planId = $result->planId;
    $output->planName = $result->planName;
    $output->country = $result->country;
    $output->legalType = $result->legalType;
    $output->legalName = $result->legalName;
    $output->registrationNumber = $result->registrationNumber;
    $output->vatNumber = $result->vatNumber;
    $output->isOwner = $result->isOwner;

    if (null !== $result->roles) {
      $roles = [];
      foreach ($result->roles as $role) {
        $roleOutput = new OrganizationMembershipRoleOutput();
        $roleOutput->id = $role->id;
        $roleOutput->label = $role->label;
        $roles[] = $roleOutput;
      }
      $output->roles = $roles;
    }

    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
}
