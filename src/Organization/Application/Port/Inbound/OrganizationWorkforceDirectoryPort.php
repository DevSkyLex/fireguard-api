<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Workforce\{OrganizationWorkforceContext, OrganizationWorkforceMember, OrganizationWorkforceMemberProfile};

/**
 * Port OrganizationWorkforceDirectoryPort.
 *
 * Organization-owned scalar contracts; callers enforce their use-case permission.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationWorkforceDirectoryPort
{
  /**
   * Reads the organization timezone and first-day-of-week settings.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return ?OrganizationWorkforceContext organization regional context, or null when unresolved
   */
  public function context(string $organizationId): ?OrganizationWorkforceContext;

  /**
   * Reads organization memberships for workload scoping and contributor checks.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<OrganizationWorkforceMember>
   */
  public function members(string $organizationId): array;

  /**
   * Resolves member labels within the owning organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $memberIds
   *
   * @return array<string, string>
   */
  public function labels(string $organizationId, array $memberIds): array;

  /**
   * Resolves presentation identities for the caller's authorized member set.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes members and roles
   * @param list<string> $memberIds authorized organization membership identifiers
   *
   * @return array<string, OrganizationWorkforceMemberProfile> profiles keyed by membership identifier
   */
  public function profiles(string $organizationId, array $memberIds): array;

  /**
   * Reads organization teams available to the workload filter.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<array{id: string, name: string}>
   */
  public function teams(string $organizationId): array;
}
