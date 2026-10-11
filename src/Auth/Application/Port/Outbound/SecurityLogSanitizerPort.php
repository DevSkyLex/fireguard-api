<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Sanitizes security log PII without exposing its framework implementation.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SecurityLogSanitizerPort
{
  public function email(?string $email): ?string;

  public function emailHash(?string $email): ?string;

  public function ip(?string $ip): ?string;

  public function ipHash(?string $ip): ?string;
}
