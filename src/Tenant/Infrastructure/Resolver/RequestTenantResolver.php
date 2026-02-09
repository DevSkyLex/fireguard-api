<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Resolver;

use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\HttpFoundation\RequestStack;
use Tenant\Application\Port\Inbound\TenantResolverPort;
use Tenant\Domain\ValueObject\TenantId;

use function is_string;
use function trim;

/**
 * Resolver RequestTenantResolver.
 *
 * Resolves tenant ID from the current HTTP request.
 *
 * @category Resolver
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestTenantResolver implements TenantResolverPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param RequestStack $requestStack the request stack
   */
  public function __construct(
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  /**
   * Method resolveTenantId.
   *
   * Resolves the tenant identifier from headers,
   * attributes, or query parameters.
   *
   * @since 1.0.0
   *
   * @return string|null the tenant ID or null when missing/invalid
   */
  public function resolveTenantId(): ?string
  {
    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      return null;
    }

    $candidate = $request->headers->get('X-Tenant-Id')
      ?? $request->headers->get('X-Tenant')
      ?? $request->attributes->get('tenantId')
      ?? $request->attributes->get('tenant_id')
      ?? $request->query->get('tenantId')
      ?? $request->query->get('tenant_id');

    if (!is_string($candidate)) {
      return null;
    }

    $candidate = trim($candidate);
    if ('' === $candidate) {
      return null;
    }

    try {
      TenantId::fromString($candidate);
    } catch (InvalidValueException) {
      return null;
    }

    return $candidate;
  }
}
