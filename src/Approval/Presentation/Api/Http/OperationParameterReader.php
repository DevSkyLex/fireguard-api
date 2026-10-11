<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Http;

use ApiPlatform\Metadata\{Operation, QueryParameterInterface};
use ApiPlatform\State\ParameterNotFound;
use Shared\Application\Contract\Http\ParsedQueryParameters;
use Symfony\Component\HttpFoundation\{InputBag, Request};

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
   * Method query.
   *
   * @since 1.0.0
   *
   * @param Operation $operation operation with parsed query values
   * @param Request|null $request current request, when available
   *
   * @return InputBag<bool|float|int|string> copied query bag; the request is never mutated
   */
  public static function query(Operation $operation, ?Request $request): InputBag
  {
    return new InputBag(ParsedQueryParameters::merge(self::collect($operation, QueryParameterInterface::class), $request?->query->all() ?? []));
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
