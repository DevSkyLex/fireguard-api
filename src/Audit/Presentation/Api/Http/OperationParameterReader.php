<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Http;

use ApiPlatform\Metadata\{Operation, QueryParameterInterface};
use ApiPlatform\State\ParameterNotFound;
use Shared\Application\Contract\Http\ParsedQueryParameters;

use function is_array;

/**
 * Service OperationParameterReader.
 *
 * Reads API Platform's parsed parameters without discarding historical query
 * keys. Existing providers retain their domain validation and pagination policy.
 *
 * @category HTTP
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OperationParameterReader
{
  // #region Methods
  /**
   * Method filters.
   *
   * @since 1.0.0
   *
   * @param Operation $operation operation with parsed parameter values
   * @param array<string, mixed> $context provider context including legacy filters
   *
   * @return array<string, mixed> query values, preserving bracketed nesting
   */
  public static function filters(Operation $operation, array $context): array
  {
    return ParsedQueryParameters::merge(
      self::collect($operation, QueryParameterInterface::class),
      is_array($context['filters'] ?? null) ? $context['filters'] : [],
    );
  }

  /**
   * Method collect
   *
   * Extracts present values for one parameter channel without merging headers
   * into query filters. Missing parameters retain the request's fallback.
   *
   * @access private
   *
   * @param Operation $operation operation with framework-parsed parameters
   * @param class-string<QueryParameterInterface> $parameterType the local framework parameter channel
   *
   * @return array<string, mixed> present values keyed by their declared name
   */
  private static function collect(Operation $operation, string $parameterType): array
  {
    $values = [];
    foreach ($operation->getParameters() ?? [] as $key => $parameter) {
      if (!$parameter instanceof $parameterType) {
        continue;
      }
      $value = $parameter->getValue();
      if (!$value instanceof ParameterNotFound) {
        $values[$key] = $value;
      }
    }

    return $values;
  }
  // #endregion
}
