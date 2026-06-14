<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

/**
 * Interface MissionChangeApplierPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MissionChangeApplierPort
{
  /**
   * Method supports.
   *
   * Executes the supports operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   *
   * @return bool the supports result
   */
  public function supports(string $resource): bool;

  /**
   * Method apply.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $resource the resource value
   * @param array<string, mixed> $patch
   */
  public function apply(string $organizationId, string $resource, array $patch): void;
}
