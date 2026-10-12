<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\ResultMessage;

use function array_key_exists;
use function explode;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_string;
use function preg_match;

/**
 * Class HttpIdempotencyResultFacts
 *
 * Validates owner result identifiers and preserves already verified authority facts.
 * It handles only nonsecret public result metadata after schema validation.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyResultFacts
{
  // #region Methods
  /**
   * Method merge
   *
   * @access public
   *
   * @param array<string, string> $verifiedFacts signed authority and result facts to preserve
   * @param mixed $newFacts runtime owner result-proof contribution
   *
   * @return array<string, string> scalar facts with unchanged verified authority
   */
  public static function merge(array $verifiedFacts, mixed $newFacts): array
  {
    if (!is_array($newFacts)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    foreach ($newFacts as $key => $value) {
      if (!is_string($key) || !is_string($value) || (array_key_exists($key, $verifiedFacts) && $verifiedFacts[$key] !== $value)) {
        throw new IdempotencyDeniedException('authorization_unavailable');
      }
      $verifiedFacts[$key] = $value;
    }

    return $verifiedFacts;
  }

  /**
   * Method identifier
   *
   * Reads a bounded owner-declared public property path after the immutable result schema has been validated.
   *
   * @access public
   *
   * @param ResultMessage $result validated primary result
   * @param string $path explicitly declared public identifier path
   * @param bool $nullable whether the owner explicitly permits a present null identifier
   *
   * @return string|null the nonsecret owner resource identifier when established
   */
  public static function identifier(ResultMessage $result, string $path, bool $nullable): ?string
  {
    if (!preg_match('/^[A-Za-z_]\w*(?:\.[A-Za-z_]\w*){0,3}$/D', $path)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $value = $result;
    foreach (explode('.', $path) as $segment) {
      $fields = is_object($value) ? get_object_vars($value) : $value;
      if (!is_array($fields) || !array_key_exists($segment, $fields)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $value = $fields[$segment];
    }
    if (null === $value && $nullable) {
      return null;
    }
    if (!is_string($value) || '' === $value) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $value;
  }

  // #endregion
}
