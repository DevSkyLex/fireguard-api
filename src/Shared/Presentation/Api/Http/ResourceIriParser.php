<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Http;

use InvalidArgumentException;

use function preg_match;
use function sprintf;

/**
 * Service ResourceIriParser.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ResourceIriParser
{
  /**
   * Method id.
   *
   * Executes the id operation.
   *
   * @since 1.0.0
   *
   * @param string $value the value value
   * @param string $resource the resource value
   *
   * @return string the id result
   */
  public static function id(string $value, string $resource): string
  {
    if (1 === preg_match('/^[0-9a-fA-F-]{36}$/', $value)) {
      return $value;
    }

    if (1 === preg_match('#/api/' . $resource . '/([^/]+)$#', $value, $matches)) {
      return $matches[1];
    }

    throw new InvalidArgumentException(sprintf('Invalid %s IRI.', $resource));
  }

  /**
   * Method memberId.
   *
   * Executes the member id operation.
   *
   * @since 1.0.0
   *
   * @param string $value the value value
   *
   * @return string the member id result
   */
  public static function memberId(string $value): string
  {
    if (1 === preg_match('/^[0-9a-fA-F-]{36}$/', $value)) {
      return $value;
    }

    if (1 === preg_match('#^/api/organizations/[^/]+/members/([^/]+)$#', $value, $matches)) {
      return $matches[1];
    }

    throw new InvalidArgumentException('Invalid organization member IRI.');
  }
}
