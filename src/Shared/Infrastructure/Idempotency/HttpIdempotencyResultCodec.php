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
    if ($value instanceof DateTimeImmutable) {
      return ['kind' => 'date', 'instant' => $value->format('Y-m-d\TH:i:s.uP'), 'timezone' => $value->getTimezone()->getName()];
    }
    if (is_array($value)) {
      $entries = [];
      foreach ($value as $key => $item) {
        $entries[] = [$key, $this->encodeValue($item, $operation, $depth + 1)];
      }

      return ['kind' => 'array', 'entries' => $entries];
    }
    if (is_object($value)) {
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
    if (null === $value || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
      return $value;
    }

    throw new IdempotencyDeniedException('result_unavailable');
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
    if ('array' === $kind && is_array($value['entries'] ?? null)) {
      $result = [];
      foreach ($value['entries'] as $entry) {
        if (!is_array($entry) || !array_key_exists(0, $entry) || !array_key_exists(1, $entry) || (!is_int($entry[0]) && !is_string($entry[0])) || array_key_exists($entry[0], $result)) {
          throw new IdempotencyDeniedException('result_unavailable');
        }
        $result[$entry[0]] = $this->decodeValue($entry[1], $operation, $depth + 1);
      }

      return $result;
    }
    if ('date' === $kind && is_string($value['instant'] ?? null) && is_string($value['timezone'] ?? null)) {
      try {
        return new DateTimeImmutable($value['instant'])->setTimezone(new DateTimeZone($value['timezone']));
      } catch (Throwable) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
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
    $fields = [];
    foreach ($value['fields'] as $name => $field) {
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
