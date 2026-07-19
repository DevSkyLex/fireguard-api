<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Domain\ValueObject\OrganizationQuotaResource;

/**
 * Port OrganizationQuotaPort.
 *
 * Inbound contract used across modules to enforce subscription quotas. Resolves
 * the quantity caps defined by an organization's current plan and the current
 * usage of each capped resource.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationQuotaPort
{
  // #region Methods
  /**
   * Method getLimit.
   *
   * Returns the cap for a resource under the organization's plan, or null when
   * the resource is unlimited.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param OrganizationQuotaResource $resource the resource to look up
   *
   * @return ?int the cap, or null when unlimited
   */
  public function getLimit(string $organizationId, OrganizationQuotaResource $resource): ?int;

  /**
   * Method getUsage.
   *
   * Returns the current quantity of a resource owned by the organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param OrganizationQuotaResource $resource the resource to count
   *
   * @return int the current usage
   */
  public function getUsage(string $organizationId, OrganizationQuotaResource $resource): int;

  /**
   * Method assertCanAdd.
   *
   * Asserts that the organization can add one more of the provided resource
   * without exceeding the plan cap.
   *
   * Serializes the check with the insert via a transaction-scoped advisory lock,
   * so it MUST be called inside the same transaction that persists the new
   * resource; otherwise the lock is released immediately and the TOCTOU race it
   * guards against reopens.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param OrganizationQuotaResource $resource the resource to add
   *
   * @throws \Organization\Domain\Exception\OrganizationQuotaExceededException
   *                                                                           when the plan cap has been reached
   */
  public function assertCanAdd(string $organizationId, OrganizationQuotaResource $resource): void;

  /**
   * Method assertCanAcceptMember.
   *
   * Asserts that accepting a pending invitation would not push the organization
   * beyond its `members` cap. Unlike {@see assertCanAdd()}, this check counts
   * only active members (not pending invitations), so the pending invitation
   * being accepted never blocks its own acceptance, while a plan downgrade that
   * left the organization already at/over its cap still blocks it.
   *
   * Like {@see assertCanAdd()} it serializes on the members advisory lock and so
   * MUST run inside the transaction that provisions the membership.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @throws \Organization\Domain\Exception\OrganizationQuotaExceededException
   *                                                                           when the active member cap has been reached
   */
  public function assertCanAcceptMember(string $organizationId): void;

  /**
   * Method getQuotaSummary.
   *
   * Returns the usage and limit of every capped resource for the organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return list<array{resource: string, used: int, limit: int|null}> the quota summary
   */
  public function getQuotaSummary(string $organizationId): array;
  // #endregion
}
