<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/**
 * Interface InterventionMemberNamingPort.
 *
 * Cross-module outbound port toward Organization, implemented by
 * `Organization\Infrastructure\Adapter\Intervention\OrganizationInterventionMemberDirectoryAdapter`.
 * Resolves organization member identifiers into display names for the
 * statistics endpoint's `byResponsible` breakdown — mirrors
 * `Messaging\Application\Port\Outbound\MessagingMemberDirectoryPort::displayNamesFor`.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionMemberNamingPort
{
  // #region Methods
  /**
   * Method displayNamesFor.
   *
   * Resolves human-readable names for a batch of organization members, in one
   * round trip.
   *
   * A member that cannot be resolved is simply absent from the result — the
   * caller decides what to show for it, and never renders a raw identifier
   * because the map said nothing.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param list<string> $memberIds the member identifiers to resolve
   *
   * @return array<string,string> display names indexed by member identifier
   */
  public function displayNamesFor(string $organizationId, array $memberIds): array;
  // #endregion
}
