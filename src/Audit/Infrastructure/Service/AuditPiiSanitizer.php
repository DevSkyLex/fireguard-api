<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash;
use function hash_hmac;
use function strtolower;
use function trim;

/**
 * Service AuditPiiSanitizer.
 *
 * Centralizes PII handling for
 * audit events.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditPiiSanitizer
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuditPiiSanitizer class.
   *
   * @since 1.0.0
   *
   * @param bool $includePii whether to include raw PII
   * @param string|null $piiSalt optional salt for hashing
   */
  public function __construct(
    #[Autowire('%env(bool:SECURITY_LOG_INCLUDE_PII)%')]
    private bool $includePii = false,
    #[Autowire('%env(default::SECURITY_LOG_PII_SALT)%')]
    private ?string $piiSalt = null,
  ) {
  }

  /**
   * Method email.
   *
   * Returns the normalized email if PII is allowed.
   *
   * @since 1.0.0
   *
   * @param string|null $email the raw email
   *
   * @return string|null the normalized email or null
   */
  public function email(?string $email): ?string
  {
    $normalized = $this->normalizeEmail($email);
    if (null === $normalized) {
      return null;
    }

    return $this->includePii ? $normalized : null;
  }

  /**
   * Method emailHash.
   *
   * Returns a hash of the normalized email.
   *
   * @since 1.0.0
   *
   * @param string|null $email the raw email
   *
   * @return string|null the hash or null
   */
  public function emailHash(?string $email): ?string
  {
    return $this->hashValue($this->normalizeEmail($email));
  }

  /**
   * Method ip.
   *
   * Returns the normalized IP if PII is allowed.
   *
   * @since 1.0.0
   *
   * @param string|null $ip the raw IP address
   *
   * @return string|null the normalized IP or null
   */
  public function ip(?string $ip): ?string
  {
    $normalized = $this->normalizeText($ip);
    if (null === $normalized) {
      return null;
    }

    return $this->includePii ? $normalized : null;
  }

  /**
   * Method ipHash.
   *
   * Returns a hash of the normalized IP.
   *
   * @since 1.0.0
   *
   * @param string|null $ip the raw IP address
   *
   * @return string|null the hash or null
   */
  public function ipHash(?string $ip): ?string
  {
    return $this->hashValue($this->normalizeText($ip));
  }

  /**
   * Method hashValue.
   *
   * Hashes a value with optional HMAC salt.
   *
   * @since 1.0.0
   *
   * @param string|null $value the value to hash
   *
   * @return string|null the hash or null
   */
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

  /**
   * Method normalizeEmail.
   *
   * Normalizes the email value for hashing.
   *
   * @since 1.0.0
   *
   * @param string|null $email the raw email
   *
   * @return string|null the normalized email or null
   */
  private function normalizeEmail(?string $email): ?string
  {
    $normalized = $this->normalizeText($email);
    if (null === $normalized) {
      return null;
    }

    return strtolower($normalized);
  }

  /**
   * Method normalizeText.
   *
   * Normalizes generic text values.
   *
   * @since 1.0.0
   *
   * @param string|null $value the raw value
   *
   * @return string|null the normalized value or null
   */
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
}
