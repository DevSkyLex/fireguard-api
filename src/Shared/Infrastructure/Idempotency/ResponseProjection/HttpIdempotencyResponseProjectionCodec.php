<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency\ResponseProjection;

use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use ReflectionClass;
use Shared\Application\Contract\Http\HttpIdempotencyQueryProjection;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Throwable;

use function array_key_exists;
use function array_keys;
use function count;
use function get_object_vars;
use function in_array;
use function is_a;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function sort;
use function strlen;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Codec HttpIdempotencyResponseProjectionCodec.
 *
 * Serializes bounded immutable application values using only each owner's explicit query/result whitelist.
 *
 * @category Codec
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HttpIdempotencyResponseProjectionCodec
{
  // #region Constants
  /**
   * Maximum bytes of one encoded query or result.
   */
  private const int MAX_BYTES = 524288;

  /**
   * Maximum application value nesting.
   */
  private const int MAX_DEPTH = 24;

  /**
   * Maximum entries across one recursively encoded value.
   */
  private const int MAX_VALUES = 8192;
  // #endregion

  // #region Methods
  /**
   * Encodes an exact query for identity comparison without instantiating cached query objects.
   *
   * @since 1.0.0
   *
   * @param QueryMessage $query the owner-declared read message
   * @param HttpIdempotencyQueryProjection $schema its exact message schema
   *
   * @return string bounded canonical message bytes
   */
  public function encodeQuery(QueryMessage $query, HttpIdempotencyQueryProjection $schema): string
  {
    if ($query::class !== $schema->queryClass) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $this->encode($query, $schema);
  }

  /**
   * Encodes a declared immutable response read result.
   *
   * @since 1.0.0
   *
   * @param ResultMessage $result the first execution's read result
   * @param HttpIdempotencyQueryProjection $schema its exact owner-declared schema
   *
   * @return string bounded typed result bytes
   */
  public function encodeResult(ResultMessage $result, HttpIdempotencyQueryProjection $schema): string
  {
    if ($result::class !== $schema->resultClass) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $this->encode($result, $schema);
  }

  /**
   * Restores a read result only after the current owner gate authorized the receipt.
   *
   * @since 1.0.0
   *
   * @param string $body the authenticated receipt's opaque result bytes
   * @param HttpIdempotencyQueryProjection $schema the current owner whitelist
   *
   * @return ResultMessage the exact captured read result
   */
  public function decodeResult(string $body, HttpIdempotencyQueryProjection $schema): ResultMessage
  {
    if (strlen($body) > self::MAX_BYTES) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    try {
      $document = json_decode($body, true, 128, JSON_THROW_ON_ERROR);
      if (!is_array($document) || 1 !== ($document['version'] ?? null) || !array_key_exists('value', $document)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $remaining = self::MAX_VALUES;
      $result = $this->decodeValue($document['value'], $schema, $remaining);
      if (!$result instanceof ResultMessage || $result::class !== $schema->resultClass) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return $result;
    } catch (Throwable) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * @since 1.0.0
   *
   * @param object $message an exact declared query or result
   * @param HttpIdempotencyQueryProjection $schema the owner whitelist
   *
   * @return string bounded encoded value
   */
  private function encode(object $message, HttpIdempotencyQueryProjection $schema): string
  {
    $remaining = self::MAX_VALUES;

    try {
      $body = json_encode(['version' => 1, 'value' => $this->encodeValue($message, $schema, $remaining)], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION, 128);
    } catch (JsonException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (strlen($body) > self::MAX_BYTES) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $body;
  }

  /**
   * @since 1.0.0
   *
   * @param mixed $value a finite immutable application field
   * @param HttpIdempotencyQueryProjection $schema the owner whitelist
   * @param int $remaining remaining recursive value budget
   * @param int $depth current application nesting
   *
   * @return mixed JSON-safe typed value
   */
  private function encodeValue(mixed $value, HttpIdempotencyQueryProjection $schema, int &$remaining, int $depth = 0): mixed
  {
    if ($depth > self::MAX_DEPTH || --$remaining < 0) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($value instanceof DateTimeImmutable) {
      if (DateTimeImmutable::class !== $value::class) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return ['kind' => 'date', 'instant' => $value->format('Y-m-d\TH:i:s.uP'), 'timezone' => $value->getTimezone()->getName()];
    }
    if (is_array($value)) {
      $entries = [];
      foreach ($value as $key => $item) {
        $entries[] = [$key, $this->encodeValue($item, $schema, $remaining, $depth + 1)];
      }

      return ['kind' => 'array', 'entries' => $entries];
    }
    if (is_object($value)) {
      $class = $value::class;
      $reflection = $this->declaredClass($class, $schema);
      if ($value instanceof BackedEnum) {
        return ['kind' => 'enum', 'class' => $class, 'value' => $value->value];
      }
      $this->assertPublicReadonly($reflection);
      $fields = [];
      foreach (get_object_vars($value) as $name => $field) {
        $fields[$name] = $this->encodeValue($field, $schema, $remaining, $depth + 1);
      }

      return ['kind' => 'object', 'class' => $class, 'fields' => $fields];
    }
    if (null === $value || is_string($value) || is_int($value) || is_bool($value) || (is_float($value) && is_finite($value))) {
      return $value;
    }

    throw new IdempotencyDeniedException('result_unavailable');
  }

  /**
   * @since 1.0.0
   *
   * @param mixed $value an encoded application field
   * @param HttpIdempotencyQueryProjection $schema the owner whitelist
   * @param int $remaining remaining recursive value budget
   * @param int $depth current application nesting
   *
   * @return mixed the restored immutable field
   */
  private function decodeValue(mixed $value, HttpIdempotencyQueryProjection $schema, int &$remaining, int $depth = 0): mixed
  {
    if ($depth > self::MAX_DEPTH || --$remaining < 0) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (!is_array($value)) {
      if (null === $value || is_string($value) || is_int($value) || is_bool($value) || (is_float($value) && is_finite($value))) {
        return $value;
      }

      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ('array' === ($value['kind'] ?? null) && is_array($value['entries'] ?? null)) {
      $items = [];
      foreach ($value['entries'] as $entry) {
        if (!is_array($entry) || 2 !== count($entry) || !array_key_exists(0, $entry) || !array_key_exists(1, $entry) || (!is_int($entry[0]) && !is_string($entry[0])) || array_key_exists($entry[0], $items)) {
          throw new IdempotencyDeniedException('result_unavailable');
        }
        $items[$entry[0]] = $this->decodeValue($entry[1], $schema, $remaining, $depth + 1);
      }

      return $items;
    }
    if ('date' === ($value['kind'] ?? null) && is_string($value['instant'] ?? null) && is_string($value['timezone'] ?? null)) {
      $instant = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $value['instant']);
      if (false === $instant || $instant->format('Y-m-d\TH:i:s.uP') !== $value['instant']) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return $instant->setTimezone(new DateTimeZone($value['timezone']));
    }
    $class = $value['class'] ?? null;
    if (!is_string($class)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $reflection = $this->declaredClass($class, $schema);
    if ('enum' === ($value['kind'] ?? null) && $reflection->isEnum() && is_a($class, BackedEnum::class, true) && (is_string($value['value'] ?? null) || is_int($value['value'] ?? null))) {
      return $class::from($value['value']);
    }
    if ('object' !== ($value['kind'] ?? null) || !is_array($value['fields'] ?? null)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $this->assertPublicReadonly($reflection);
    $constructorNames = [];
    foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
      $constructorNames[] = $parameter->getName();
    }
    $fieldNames = array_keys($value['fields']);
    sort($constructorNames);
    sort($fieldNames);
    if ($constructorNames !== $fieldNames) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $fields = [];
    foreach ($value['fields'] as $name => $field) {
      if (!is_string($name)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $fields[$name] = $this->decodeValue($field, $schema, $remaining, $depth + 1);
    }

    return $reflection->newInstanceArgs($fields);
  }

  /**
   * @since 1.0.0
   *
   * @param string $class an encountered object class
   * @param HttpIdempotencyQueryProjection $schema the current whitelist
   *
   * @return ReflectionClass<object> reflection for an explicitly declared class
   */
  private function declaredClass(string $class, HttpIdempotencyQueryProjection $schema): ReflectionClass
  {
    if ($class !== $schema->queryClass && $class !== $schema->resultClass && !in_array($class, $schema->contractClasses, true)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return new ReflectionClass($class);
  }

  /**
   * @since 1.0.0
   *
   * @param ReflectionClass<object> $reflection the explicitly allowed application class
   *
   * @return void
   */
  private function assertPublicReadonly(ReflectionClass $reflection): void
  {
    if (!$reflection->isReadOnly()) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    foreach ($reflection->getProperties() as $property) {
      if (!$property->isPublic() || $property->isStatic()) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
  }
  // #endregion
}
