<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Logging;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash;
use function hash_hmac;
use function strtolower;
use function trim;

/**
 * Service SecurityLogSanitizer.
 *
 * Centralizes PII handling for
 * security logs.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SecurityLogSanitizer
{
  // #region Constructor
  public function __construct(
    #[Autowire('%env(bool:SECURITY_LOG_INCLUDE_PII)%')]
    private bool $includePii = false,
    #[Autowire('%env(default::SECURITY_LOG_PII_SALT)%')]
    private ?string $piiSalt = null,
  ) {
  }
  // #endregion

  // #region Methods
  public function email(?string $email): ?string
  {
    $normalized = $this->normalizeEmail($email);
    if (null === $normalized) {
      return null;
    }

    return $this->includePii ? $normalized : null;
  }

  public function emailHash(?string $email): ?string
  {
    return $this->hashValue($this->normalizeEmail($email));
  }

  public function ip(?string $ip): ?string
  {
    $normalized = $this->normalizeText($ip);
    if (null === $normalized) {
      return null;
    }

    return $this->includePii ? $normalized : null;
  }

  public function ipHash(?string $ip): ?string
  {
    return $this->hashValue($this->normalizeText($ip));
  }

  private function hashValue(?string $value): ?string
  {
    if (null === $value) {
      return null;
    }

    if (null !== $this->piiSalt && '' !== $this->piiSalt) {
      return hash_hmac('sha256', $value, $this->piiSalt);
    }

    return hash('sha256', $value);
  }

  private function normalizeEmail(?string $email): ?string
  {
    $normalized = $this->normalizeText($email);
    if (null === $normalized) {
      return null;
    }

    return strtolower($normalized);
  }

  private function normalizeText(?string $value): ?string
  {
    if (null === $value) {
      return null;
    }

    $trimmed = trim($value);
    if ('' === $trimmed) {
      return null;
    }

    return $trimmed;
  }
  // #endregion
}
