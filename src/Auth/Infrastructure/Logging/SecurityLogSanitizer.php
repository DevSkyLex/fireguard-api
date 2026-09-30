<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Logging;

use Auth\Infrastructure\Exception\MissingPiiSaltException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function hash_hmac;
use function strtolower;
use function trim;

/**
 * Class SecurityLogSanitizer
 *
 * Centralizes PII handling for
 * security logs.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SecurityLogSanitizer
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Refuses to start without a non-blank secret salt because an unsalted digest would expose low-entropy personal data.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $includePii whether to include raw PII
   * @param string $piiSalt the salt for hashing; must not be blank
   *
   * @return void
   *
   * @throws MissingPiiSaltException when the salt is blank
   */
  public function __construct(
    #[Autowire('%env(bool:SECURITY_LOG_INCLUDE_PII)%')]
    private bool $includePii = false,
    #[Autowire('%env(SECURITY_LOG_PII_SALT)%')]
    private string $piiSalt = '',
  ) {
    // Refusing beats degrading. Until 2026-08-27 a blank salt fell through to a
    // bare hash('sha256', $email), which is not a privacy measure: the input space
    // is a wordlist of email addresses, so the hash is reversible by anyone
    // holding the logs. The salt was blank in EVERY env file in the repository, so
    // that is what shipped. There is no safe fallback to pick here -- an unsalted
    // digest and no digest at all are both worse than not starting.
    if ('' === trim($this->piiSalt)) {
      throw new MissingPiiSaltException(
        'SECURITY_LOG_PII_SALT is blank. It keys the HMAC that hashes personal data in '
        . 'audit events and security logs; without it the digest is a plain sha256 of the '
        . 'value and trivially reversible. Set it to a random secret.',
      );
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method email
   *
   * Returns the normalized email only when configuration permits raw PII in security logs.
   *
   * @access public
   *
   * @param ?string $email the supplied email address
   *
   * @return ?string the normalized address when PII logging is enabled, otherwise null
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
   * Method emailHash
   *
   * Returns an HMAC-SHA-256 digest of the normalized email without exposing the address.
   *
   * @access public
   *
   * @param ?string $email the supplied email address
   *
   * @return ?string the keyed digest, or null for missing or blank input
   */
  public function emailHash(?string $email): ?string
  {
    return $this->hashValue($this->normalizeEmail($email));
  }

  /**
   * Method ip
   *
   * Returns the trimmed address only when configuration permits raw PII in security logs.
   *
   * @access public
   *
   * @param ?string $ip the supplied IP address
   *
   * @return ?string the trimmed address when PII logging is enabled, otherwise null
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
   * Method ipHash
   *
   * Returns an HMAC-SHA-256 digest of the trimmed IP address without exposing it.
   *
   * @access public
   *
   * @param ?string $ip the supplied IP address
   *
   * @return ?string the keyed digest, or null for missing or blank input
   */
  public function ipHash(?string $ip): ?string
  {
    return $this->hashValue($this->normalizeText($ip));
  }

  /**
   * Method hashValue
   *
   * Applies the configured secret salt as an HMAC key so low-entropy PII is not exposed by an unsalted digest.
   *
   * @access private
   *
   * @param ?string $value normalized value to hash
   *
   * @return ?string the keyed SHA-256 digest, or null when the value is absent
   */
  private function hashValue(?string $value): ?string
  {
    if (null === $value) {
      return null;
    }

    return hash_hmac('sha256', $value, $this->piiSalt);
  }

  /**
   * Method normalizeEmail
   *
   * Trims an email and lowercases it before logging or hashing for stable comparisons.
   *
   * @access private
   *
   * @param ?string $email the input email address
   *
   * @return ?string the normalized address, or null for missing or blank input
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
   * Method normalizeText
   *
   * Trims text and represents missing or whitespace-only values as null.
   *
   * @access private
   *
   * @param ?string $value the supplied text
   *
   * @return ?string the trimmed non-blank text, or null
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
  // #endregion
}
