<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;

/**
 * Port AssistantThreadRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AssistantThreadRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an assistant thread aggregate.
   *
   * @since 1.0.0
   *
   * @param AssistantThread $thread the assistant thread aggregate
   */
  public function save(AssistantThread $thread): void;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadId $id the assistant thread identifier
   *
   * @return ?AssistantThread the assistant thread when found
   */
  public function findById(AssistantThreadId $id): ?AssistantThread;

  /**
   * Method listByOrganizationAndMember.
   *
   * Lists a member's OWN threads within an organization, most recent
   * activity first. Never returns another member's thread — there is no
   * shared/organization-wide assistant thread in this design.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the owning member identifier
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<AssistantThread> the matching assistant threads
   */
  public function listByOrganizationAndMember(string $organizationId, string $memberId, int $limit, int $offset): array;

  /**
   * Method countByOrganizationAndMember.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the owning member identifier
   *
   * @return int the matching assistant thread count
   */
  public function countByOrganizationAndMember(string $organizationId, string $memberId): int;
  // #endregion
}
