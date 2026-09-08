<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\FeedToken;

use Calendar\Domain\Model\FeedToken\CalendarFeedToken;

/**
 * Port CalendarFeedTokenRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CalendarFeedTokenRepositoryPort
{
  /**
   * Method save.
   *
   * Persists the feed token aggregate (insert or update).
   *
   * @since 1.0.0
   *
   * @param CalendarFeedToken $token the feed token to persist
   */
  public function save(CalendarFeedToken $token): void;

  /**
   * Method findActiveByOrganizationAndUser.
   *
   * Finds the single non-revoked token of a member in an organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return ?CalendarFeedToken the active token, or null when none
   */
  public function findActiveByOrganizationAndUser(string $organizationId, string $userId): ?CalendarFeedToken;

  /**
   * Method findActiveByTokenHash.
   *
   * Finds a non-revoked token by the SHA-256 hash of its secret.
   *
   * @since 1.0.0
   *
   * @param string $tokenHash the hashed secret
   *
   * @return ?CalendarFeedToken the active token, or null when unknown or revoked
   */
  public function findActiveByTokenHash(string $tokenHash): ?CalendarFeedToken;
}
