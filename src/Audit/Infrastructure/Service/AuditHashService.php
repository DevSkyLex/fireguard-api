<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Service;

use function array_is_list;
use function array_map;
use function hash;
use function is_array;
use function json_encode;
use function ksort;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Service AuditHashService.
 *
 * Computes deterministic hashes
 * for audit chain integrity.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuditHashService
{
  /**
   * Method compute.
   *
   * Computes a deterministic hash for a payload,
   * chained with the previous hash.
   *
   * @since 1.0.0
   *
   * @param string $prevHash the previous hash in the chain
   * @param array<string, mixed> $payload the event payload
   *
   * @return string the computed hash
   */
  public function compute(string $prevHash, array $payload): string
  {
    $normalized = $this->normalize($payload);
    $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return hash('sha256', $prevHash . '|' . $json);
  }

  /**
   * Method normalize.
   *
   * Normalizes a payload for deterministic hashing.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload
   *
   * @return array<string, mixed>
   */
  private function normalize(array $payload): array
  {
    $normalized = [];

    foreach ($payload as $key => $value) {
      if (is_array($value)) {
        $normalized[$key] = $this->normalizeArray($value);
      } else {
        $normalized[$key] = $value;
      }
    }

    ksort($normalized);

    return $normalized;
  }

  /**
   * Method normalizeArray.
   *
   * Normalizes nested arrays and preserves list order.
   *
   * @since 1.0.0
   *
   * @param array<mixed> $value
   *
   * @return array<mixed>
   */
  private function normalizeArray(array $value): array
  {
    if (array_is_list($value)) {
      return array_map(
        fn ($item) => is_array($item) ? $this->normalizeArray($item) : $item,
        $value,
      );
    }

    $normalized = [];
    foreach ($value as $key => $item) {
      $normalized[$key] = is_array($item) ? $this->normalizeArray($item) : $item;
    }
    ksort($normalized);

    return $normalized;
  }
}
