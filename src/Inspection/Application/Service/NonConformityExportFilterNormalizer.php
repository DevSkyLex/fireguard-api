<?php

declare(strict_types=1);

namespace Inspection\Application\Service;

use Inspection\Domain\ValueObject\{NonConformitySeverity, NonConformityStatus};
use InvalidArgumentException;

use function is_array;
use function is_string;

/** Validates export filters after the report access decision. @category Service */
final readonly class NonConformityExportFilterNormalizer
{
  /**
   * @param array<string, mixed> $query requested filters
   *
   * @return array<string, string> validated export predicates
   */
  public function normalize(array $query): array
  {
    $filters = [];
    foreach (['severity', 'status'] as $key) {
      $value = $query[$key] ?? null;
      if (is_array($value)) {
        throw new InvalidArgumentException('Input value "' . $key . '" contains a non-scalar value.');
      }
      if (!is_string($value) || '' === $value) {
        continue;
      }
      if ('severity' === $key && null === NonConformitySeverity::tryFrom($value)) {
        throw new InvalidArgumentException('The severity filter must be one of: low, medium, high, critical.');
      }
      if ('status' === $key && null === NonConformityStatus::tryFrom($value)) {
        throw new InvalidArgumentException('The status filter must be one of: open, in_progress, done, waived.');
      }
      $filters[$key] = $value;
    }

    return $filters;
  }
}
