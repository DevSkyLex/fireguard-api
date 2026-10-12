<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use ReflectionClass;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\ResultMessage;
use Throwable;

use function array_key_exists;
use function class_exists;
use function get_object_vars;
use function in_array;
use function is_a;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * Class HttpIdempotencyResultCodec
 *
 * Encodes immutable application outcomes using an explicit owner-declared class schema.
 * Reconstitution invokes only declared result/contract constructors; PHP object unserialization is never used.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyResultCodec
{
  // #region Methods
  /**
   * Method encode
   *
   * @access public
   *
   * @param ResultMessage $result the primary application outcome
   * @param HttpIdempotencyOperation $operation the owner-declared exact result schema
   *
   * @return string opaque versioned application-result bytes
   */
  public function encode(ResultMessage $result, HttpIdempotencyOperation $operation): string
  {
    if ($result::class !== $operation->resultClass) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    try {
      $body = json_encode(['version' => 1, 'result' => $this->encodeValue($result, $operation)], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
      // Validate constructor compatibility while the owning mutation can still roll back.
      if ($this->encodeValue($this->decode($body, $operation), $operation) !== $this->encodeValue($result, $operation)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return $body;
    } catch (JsonException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method decode
   *
   * Callers must complete the current owner authorization gate before passing a cached body here.
   * Invalid versions, malformed payloads and undeclared classes fail closed.
   *
   * @access public
   *
   * @param string $body the opaque committed result bytes
   * @param HttpIdempotencyOperation $operation the currently declared result schema
   *
   * @return ResultMessage the original application result
   */
  public function decode(string $body, HttpIdempotencyOperation $operation): ResultMessage
  {
    try {
      $document = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
      if (!is_array($document) || 1 !== ($document['version'] ?? null) || !array_key_exists('result', $document)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $result = $this->decodeValue($document['result'], $operation);
      if (!$result instanceof ResultMessage || $result::class !== $operation->resultClass) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return $result;
    } catch (JsonException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method withProjection
   *
   * Adds an opaque owner-declared query projection captured before the same final commit.
   *
   * @access public
   *
   * @param string $body the encoded primary application result
   * @param string $projection the bounded application query projection payload
   *
   * @return string the complete versioned application-result bundle
   */
  public function withProjection(string $body, string $projection): string
  {
    try {
      $document = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
      if (!is_array($document) || 1 !== ($document['version'] ?? null)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $document['projection'] = $projection;

      return json_encode($document, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    } catch (JsonException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method projection
   *
   * @access public
   *
   * @param string $body the complete committed application-result bundle
   *
   * @return string|null the declared query projection payload when present
   */
  public function projection(string $body): ?string
  {
    try {
      $document = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
      if (!is_array($document) || 1 !== ($document['version'] ?? null)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $projection = $document['projection'] ?? null;
      if (null !== $projection && !is_string($projection)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return $projection;
    } catch (JsonException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method encodeValue
   *
   * @access private
   *
   * @param mixed $value one immutable application result field
   * @param HttpIdempotencyOperation $operation the declared result schema
   * @param int $depth current recursive depth
   *
   * @return mixed finite JSON-safe encoding
   */
  private function encodeValue(mixed $value, HttpIdempotencyOperation $operation, int $depth = 0): mixed
  {
    if ($depth > 32) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return match (true) {
      $value instanceof DateTimeImmutable => ['kind' => 'date', 'instant' => $value->format('Y-m-d\TH:i:s.uP'), 'timezone' => $value->getTimezone()->getName()],
      is_array($value) => $this->encodeArray($value, $operation, $depth),
      is_object($value) => $this->encodeObject($value, $operation, $depth),
      null === $value || is_string($value) || is_int($value) || is_float($value) || is_bool($value) => $value,
      default => throw new IdempotencyDeniedException('result_unavailable'),
    };
  }

  /**
   * Method encodeArray
   *
   * @access private
   *
   * @param array<array-key, mixed> $value the ordered application collection
   * @param HttpIdempotencyOperation $operation the declared result schema
   * @param int $depth current recursive depth
   *
   * @return array{kind: string, entries: list<array{array-key, mixed}>} typed collection encoding
   */
  private function encodeArray(array $value, HttpIdempotencyOperation $operation, int $depth): array
  {
    $entries = [];
    foreach ($value as $key => $item) {
      $entries[] = [$key, $this->encodeValue($item, $operation, $depth + 1)];
    }

    return ['kind' => 'array', 'entries' => $entries];
  }

  /**
   * Method encodeObject
   *
   * @access private
   *
   * @param object $value the immutable declared application value
   * @param HttpIdempotencyOperation $operation the declared result schema
   * @param int $depth current recursive depth
   *
   * @return array<string, mixed> typed declared object encoding
   */
  private function encodeObject(object $value, HttpIdempotencyOperation $operation, int $depth): array
  {
    $class = $this->declaredClass($value::class, $operation);
    if ($value instanceof BackedEnum) {
      return ['kind' => 'enum', 'class' => $class, 'value' => $value->value];
    }
    $reflection = new ReflectionClass($class);
    if (!$reflection->isReadOnly()) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $fields = [];
    foreach (get_object_vars($value) as $name => $field) {
      $fields[$name] = $this->encodeValue($field, $operation, $depth + 1);
    }

    return ['kind' => 'object', 'class' => $class, 'fields' => $fields];
  }

  /**
   * Method decodeValue
   *
   * @access private
   *
   * @param mixed $value one encoded result field
   * @param HttpIdempotencyOperation $operation the declared result schema
   * @param int $depth current recursive depth
   *
   * @return mixed the reconstituted immutable field
   */
  private function decodeValue(mixed $value, HttpIdempotencyOperation $operation, int $depth = 0): mixed
  {
    if ($depth > 32) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (!is_array($value)) {
      return $value;
    }
    $kind = $value['kind'] ?? null;

    return match (true) {
      'array' === $kind && is_array($value['entries'] ?? null) => $this->decodeArray($value['entries'], $operation, $depth),
      'date' === $kind && is_string($value['instant'] ?? null) && is_string($value['timezone'] ?? null) => $this->decodeDate($value['instant'], $value['timezone']),
      default => $this->decodeObject($value, $operation, $depth),
    };
  }

  /**
   * Method decodeArray
   *
   * @access private
   *
   * @param array<array-key, mixed> $entries the encoded ordered entries
   * @param HttpIdempotencyOperation $operation the declared result schema
   * @param int $depth current recursive depth
   *
   * @return array<array-key, mixed> the restored collection
   */
  private function decodeArray(array $entries, HttpIdempotencyOperation $operation, int $depth): array
  {
    $result = [];
    foreach ($entries as $entry) {
      if (!is_array($entry) || !array_key_exists(0, $entry) || !array_key_exists(1, $entry) || (!is_int($entry[0]) && !is_string($entry[0])) || array_key_exists($entry[0], $result)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $result[$entry[0]] = $this->decodeValue($entry[1], $operation, $depth + 1);
    }

    return $result;
  }

  /**
   * Method decodeDate
   *
   * @access private
   *
   * @param string $instant the encoded immutable instant
   * @param string $timezone the encoded timezone
   *
   * @return DateTimeImmutable the restored date
   */
  private function decodeDate(string $instant, string $timezone): DateTimeImmutable
  {
    try {
      return new DateTimeImmutable($instant)->setTimezone(new DateTimeZone($timezone));
    } catch (Throwable) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method decodeObject
   *
   * @access private
   *
   * @param array<array-key, mixed> $value the encoded declared value
   * @param HttpIdempotencyOperation $operation the declared result schema
   * @param int $depth current recursive depth
   *
   * @return object the declared immutable value
   */
  private function decodeObject(array $value, HttpIdempotencyOperation $operation, int $depth): object
  {
    $kind = $value['kind'] ?? null;
    $class = $value['class'] ?? null;
    if (!is_string($class)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $class = $this->declaredClass($class, $operation);
    $reflection = new ReflectionClass($class);
    if ('enum' === $kind && $reflection->isEnum() && is_a($class, BackedEnum::class, true) && (is_string($value['value'] ?? null) || is_int($value['value'] ?? null))) {
      try {
        return $class::from($value['value']);
      } catch (Throwable) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
    if ('object' !== $kind || !$reflection->isReadOnly() || !is_array($value['fields'] ?? null)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $this->decodeFields($reflection, $value['fields'], $operation, $depth);
  }

  /**
   * Method decodeFields
   *
   * @access private
   *
   * @param ReflectionClass<object> $reflection the declared readonly class
   * @param array<array-key, mixed> $values encoded constructor values
   * @param HttpIdempotencyOperation $operation the declared result schema
   * @param int $depth current recursive depth
   *
   * @return object the reconstructed application value
   */
  private function decodeFields(ReflectionClass $reflection, array $values, HttpIdempotencyOperation $operation, int $depth): object
  {
    $fields = [];
    foreach ($values as $name => $field) {
      if (!is_string($name)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $fields[$name] = $this->decodeValue($field, $operation, $depth + 1);
    }

    try {
      return $reflection->newInstanceArgs($fields);
    } catch (Throwable) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method declaredClass
   *
   * @access private
   *
   * @param string $class the encountered class name
   * @param HttpIdempotencyOperation $operation the owner-approved result schema
   *
   * @return class-string the validated schema class
   */
  private function declaredClass(string $class, HttpIdempotencyOperation $operation): string
  {
    if (!class_exists($class) || ($class !== $operation->resultClass && !in_array($class, $operation->contractClasses, true))) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $class;
  }
  // #endregion
}
