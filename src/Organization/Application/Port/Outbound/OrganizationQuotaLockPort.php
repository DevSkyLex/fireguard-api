<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\ValueObject\OrganizationQuotaResource;

/**
 * Port OrganizationQuotaLockPort.
 *
 * Outbound contract used to serialize a read-then-write over one organization
 * resource, closing check/write TOCTOU races. Two of them ride on this lock:
 *
 * - the quota check/insert race: two concurrent creators sitting at
 *   plan_limit - 1 must not both pass the count and both insert;
 * - the last-administrator race: two concurrent removals must not both read
 *   "another administrator remains" and both deactivate one
 *   (see {@see \Organization\Application\Service\OrganizationLastAdminGuardService}).
 *
 * Both take the MEMBERS key, so a member addition and an administrator removal
 * contend on the same lock — extra serialization on one organization's member
 * set, never an incorrect result.
 *
 * The lock is transaction-scoped: it is acquired for the duration of the current
 * database transaction and released automatically on commit or rollback. Callers
 * MUST therefore invoke {@see acquire()} inside the same transaction that performs
 * the write, so a concurrent writer blocks until the first one commits and then
 * observes the committed state when it reads.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationQuotaLockPort
{
  // #region Methods
  /**
   * Method acquire.
   *
   * Acquires the transaction-scoped advisory lock guarding the given resource of
   * an organization, blocking until any concurrent holder releases it. The lock
   * is keyed per (organization, resource) so unrelated resources and tenants do
   * not contend, and is released automatically when the surrounding transaction
   * ends.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param OrganizationQuotaResource $resource the capped resource being guarded
   */
  public function acquire(string $organizationId, OrganizationQuotaResource $resource): void;
  // #endregion
}
