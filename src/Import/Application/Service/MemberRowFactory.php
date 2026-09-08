<?php

declare(strict_types=1);

namespace Import\Application\Service;

use Import\Domain\Exception\ImportRowValidationException;
use Organization\Application\Contract\Provisioning\ProvisionMemberInvitationRequest;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function trim;

/**
 * Service MemberRowFactory.
 *
 * Builds a {@see ProvisionMemberInvitationRequest} from one associative CSV
 * data row. Expected header: `email` (required), `roles` (optional — organization
 * role *names* separated by `|`; blank or absent means the organization's
 * default `member` role). Unknown columns are ignored. Only structural
 * validation happens here (the required column present); email syntax, role
 * existence, conflicts and the member-cap quota are left to
 * `InviteOrganizationMemberHandler` (via `MemberInvitationProvisioningPort`),
 * which already reports each failure as a distinct non-fatal outcome — this
 * factory never depends on Organization's Domain types.
 *
 * Named "Factory" (not "Mapper") for the same reason as
 * {@see EquipmentRowFactory}: the "Mapper" suffix is reserved for
 * `Infrastructure/Persistence/` Doctrine record mappers in this codebase.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MemberRowFactory
{
  // #region Methods
  /**
   * Method map.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $invitedByUserId the inviting user identifier (the import job's creator)
   * @param array<string, string> $row the associative CSV data row
   *
   * @throws ImportRowValidationException when the required `email` column is missing or blank
   *
   * @return ProvisionMemberInvitationRequest the mapped provisioning request
   */
  public function map(string $organizationId, string $invitedByUserId, array $row): ProvisionMemberInvitationRequest
  {
    $email = trim($row['email'] ?? '');
    if ('' === $email) {
      throw ImportRowValidationException::missingRequiredColumn('email');
    }

    return new ProvisionMemberInvitationRequest(
      organizationId: $organizationId,
      email: $email,
      invitedByUserId: $invitedByUserId,
      roleNames: $this->roleNames($row['roles'] ?? ''),
    );
  }

  /**
   * Method roleNames.
   *
   * Splits the pipe-separated role names, trimming each and dropping blanks
   * — `"admin| manager |"` yields `['admin', 'manager']`, and a blank cell
   * yields `[]` (the default-role fallback).
   *
   * @since 1.0.0
   *
   * @param string $roles the raw `roles` cell value
   *
   * @return list<string> the cleaned role names
   */
  private function roleNames(string $roles): array
  {
    if ('' === trim($roles)) {
      return [];
    }

    return array_values(array_filter(
      array_map(static fn (string $name): string => trim($name), explode('|', $roles)),
      static fn (string $name): bool => '' !== $name,
    ));
  }
  // #endregion
}
