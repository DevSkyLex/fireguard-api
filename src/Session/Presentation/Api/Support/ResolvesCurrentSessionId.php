<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Support;

use Symfony\Component\HttpFoundation\Request;

/**
 * Trait ResolvesCurrentSessionId.
 *
 * Resolves the identifier of the HTTP session backing the current request,
 * used to distinguish "this device" from the caller's other active Session
 * aggregates. Shared by every Provider/Processor that needs to know which
 * session is "current" so there is a single source of truth for that
 * resolution.
 *
 * @category Support
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
trait ResolvesCurrentSessionId
{
  // #region Methods
  /**
   * Method resolveCurrentSessionId.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the API Platform provider/processor context
   *
   * @return string the current session ID, or an empty string when it cannot be resolved
   */
  private function resolveCurrentSessionId(array $context): string
  {
    $request = isset($context['request']) && $context['request'] instanceof Request
      ? $context['request']
      : null;

    return $request?->getSession()->getId() ?? '';
  }
  // #endregion
}
