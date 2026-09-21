<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Http;

use ApiPlatform\Metadata\{HeaderParameterInterface, Operation, QueryParameterInterface};
use ApiPlatform\State\ParameterNotFound;
use Symfony\Component\HttpFoundation\{HeaderBag, InputBag, Request};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_pop;
use function is_array;
use function is_string;
use function preg_match_all;

/**
 * Service OperationParameterReader.
 *
 * Reads API Platform's parsed parameters without discarding historical query
 * keys. Existing providers retain their domain validation and pagination policy.
 *
 * @category HTTP
 *
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
    $values = is_array($context['filters'] ?? null) ? $context['filters'] : [];
    foreach ($operation->getParameters() ?? [] as $key => $parameter) {
      if (!$parameter instanceof QueryParameterInterface) {
        continue;
      }

      $value = $parameter->getValue();
      if ($value instanceof ParameterNotFound) {
        continue;
      }

      preg_match_all('/[^\[\]]+/', $key, $matches);
      $segments = $matches[0];
      $leaf = array_pop($segments);
      if (null === $leaf) {
        continue;
      }

      $target = &$values;
      foreach ($segments as $segment) {
        if (!is_array($target[$segment] ?? null)) {
          $target[$segment] = [];
        }
        $target = &$target[$segment];
      }
      $target[$leaf] = $value;
      unset($target);
    }

    /** @var array<string, mixed> $values HTTP query parameter names */
    return $values;
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
    foreach ($operation->getParameters() ?? [] as $key => $parameter) {
      if (!$parameter instanceof HeaderParameterInterface) {
        continue;
      }

      $value = $parameter->getValue();
      if ($value instanceof ParameterNotFound) {
        continue;
      }
      if (null !== $value && !is_string($value) && !is_array($value)) {
        throw new BadRequestHttpException('Invalid header value.');
      }
      if (is_array($value)) {
        $entries = [];
        foreach ($value as $entry) {
          if (!is_string($entry)) {
            throw new BadRequestHttpException('Invalid header value.');
          }
          $entries[] = $entry;
        }
        $value = $entries;
      }
      $headers->set($key, $value);
    }

    return $headers;
  }
  // #endregion
}
