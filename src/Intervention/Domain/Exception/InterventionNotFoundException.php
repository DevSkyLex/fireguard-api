<?php

declare(strict_types=1);

namespace Intervention\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception InterventionNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionNotFoundException extends RuntimeException
{
  /**
   * Method withId.
   *
   * Executes the with id operation.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   *
   * @return self the with id result
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Intervention with ID "%s" not found.', $id));
  }

  /**
   * Method forOrganizationScope.
   *
   * Builds the exception for an organization the caller is not an active
   * member of, on a route whose scope the caller supplied directly (a
   * listing or a creation) rather than reaching through a record identifier.
   *
   * Deliberately the SAME class, and therefore the same 404, that an unknown
   * identifier produces: a distinct status here would tell an outsider which
   * organization identifiers are real.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the with organization scope result
   */
  public static function forOrganizationScope(string $organizationId): self
  {
    return new self(sprintf('Organization with ID "%s" not found.', $organizationId));
  }

  /**
   * Method forTeam.
   *
   * Builds the exception for a team the caller referenced by identifier in a
   * payload — unknown, malformed, or owned by another organization.
   *
   * Deliberately the SAME class, and therefore the same 404, in all three
   * cases: a distinct status for a real team would tell a caller which team
   * identifiers exist outside their own organization.
   *
   * @since 1.2.0
   *
   * @param string $teamId the team identifier
   *
   * @return self the for team result
   */
  public static function forTeam(string $teamId): self
  {
    return new self(sprintf('Team with ID "%s" not found.', $teamId));
  }
}
