<?php

declare(strict_types=1);

namespace Tenant\Application\Port\Inbound;

/**
 * Port TenantResolverPort.
 *
 * Resolves the current tenant identifier
 * from the execution context (request, token, etc.).
 *
 * @category Inbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TenantResolverPort
{
  /**
   * Resolves the current tenant identifier.
   *
   * @since 1.0.0
   *
   * @return string|null the tenant ID or null when not available
   */
  public function resolveTenantId(): ?string;
}
