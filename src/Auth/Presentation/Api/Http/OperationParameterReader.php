<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Http;

use ApiPlatform\Metadata\{HeaderParameterInterface, Operation, QueryParameterInterface};
use ApiPlatform\State\ParameterNotFound;
use InvalidArgumentException;
use Shared\Application\Contract\Http\{ParsedHeaderValue, ParsedQueryParameters};
use Symfony\Component\HttpFoundation\{HeaderBag, InputBag, Request};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
    return new InputBag(self::filters($operation, ['filters' => $request?->query->all() ?? []]));
  }

  /**
   * Method headers.
   *
   * @since 1.0.0
   *
   * @param Operation $operation operation with parsed header values
   * @param Request|null $request current request, when available
   *
   * @return HeaderBag copied headers preserving conditional-request syntax
   */
  public static function headers(Operation $operation, ?Request $request): HeaderBag
  {
    $headers = new HeaderBag($request?->headers->all() ?? []);
    foreach (self::collect($operation, HeaderParameterInterface::class) as $key => $value) {
      try {
        $headers->set($key, ParsedHeaderValue::normalize($value));
      } catch (InvalidArgumentException) {
        throw new BadRequestHttpException('Invalid header value.');
      }
    }

    return $headers;
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
   * @param class-string<HeaderParameterInterface|QueryParameterInterface> $parameterType the local framework parameter channel
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
