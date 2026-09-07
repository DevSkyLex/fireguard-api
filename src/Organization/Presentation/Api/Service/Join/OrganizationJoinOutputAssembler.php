<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Service\Join;

use ApiPlatform\Metadata\Operation;
use LogicException;

use function is_array;
use function is_string;
use function property_exists;

/**
 * Assembler OrganizationJoinOutputAssembler.
 *
 * @category Assembler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinOutputAssembler
{
  /**
   * @since 1.0.0
   *
   * @param Operation $operation resource metadata
   * @param array<string,mixed> $values projected values
   *
   * @return object declared output DTO
   */
  public function assemble(Operation $operation, array $values): object
  {
    $metadata = $operation->getOutput();
    $class = is_string($metadata) ? $metadata : (is_array($metadata) ? ($metadata['class'] ?? null) : null);
    if (!is_string($class)) {
      throw new LogicException('Missing join output contract.');
    }
    $output = new $class();
    foreach ($values as $property => $value) {
      if (!property_exists($output, $property)) {
        throw new LogicException('Unexpected join output field.');
      }
      $output->{$property} = $value;
    }

    return $output;
  }
}
