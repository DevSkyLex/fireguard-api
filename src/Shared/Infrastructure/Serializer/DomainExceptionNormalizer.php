<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Serializer;

use Shared\Domain\Exception\DomainException;
use Shared\Domain\Exception\EntityNotFoundException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Throwable;

use function preg_replace;
use function str_contains;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Normalizer DomainExceptionNormalizer.
 *
 * @category Normalizer
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DomainExceptionNormalizer implements NormalizerInterface
{
  // #region Methods
  /**
   * Method normalize
   * {@inheritDoc}
   *
   * @param mixed                $object  the exception to normalize
   * @param string|null          $format  the format
   * @param array<string, mixed> $context the context
   *
   * @return array<string, mixed> the normalized data
   */
  public function normalize(mixed $object, ?string $format = null, array $context = []): array
  {
    if (!$object instanceof Throwable) {
      return [];
    }

    $statusCode = $this->getStatusCode($object);

    return [
      '@context' => '/api/contexts/Error',
      '@type' => 'hydra:Error',
      'hydra:title' => $this->getTitle($object),
      'hydra:description' => $object->getMessage(),
      'status' => $statusCode,
      'type' => $this->getType($object),
      'detail' => $object->getMessage(),
      'violations' => $this->getViolations($object),
    ];
  }

  /**
   * Method supportsNormalization
   * {@inheritDoc}
   *
   * @param mixed                $data    the data
   * @param string|null          $format  the format
   * @param array<string, mixed> $context the context
   *
   * @return bool whether normalization is supported
   */
  public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
  {
    return $data instanceof DomainException
      || ($data instanceof FlattenException && $this->isFromDomainException($data));
  }

  /**
   * Method getSupportedTypes
   * {@inheritDoc}
   *
   * @param string|null $format the format
   *
   * @return array<class-string|'*', bool|null> the supported types
   */
  public function getSupportedTypes(?string $format): array
  {
    return [
      DomainException::class => true,
      EntityNotFoundException::class => true,
      FlattenException::class => true,
    ];
  }

  /**
   * Method getStatusCode.
   *
   * Gets the HTTP status code for the exception.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception
   *
   * @return int the status code
   */
  private function getStatusCode(Throwable $exception): int
  {
    if ($exception instanceof EntityNotFoundException) {
      return 404;
    }

    if ($exception instanceof FlattenException) {
      return $exception->getStatusCode();
    }

    return 400;
  }

  /**
   * Method getTitle.
   *
   * Gets the error title.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception
   *
   * @return string the title
   */
  private function getTitle(Throwable $exception): string
  {
    if ($exception instanceof EntityNotFoundException) {
      return 'Resource Not Found';
    }

    return 'Domain Error';
  }

  /**
   * Method getType.
   *
   * Gets the error type URI.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception
   *
   * @return string the type URI
   */
  private function getType(Throwable $exception): string
  {
    $className = $exception::class;
    $shortName = substr($className, strrpos($className, '\\') + 1);

    return '/errors/' . $this->toSnakeCase($shortName);
  }

  /**
   * Method getViolations.
   *
   * Gets any constraint violations.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception
   *
   * @return list<array<string, string>> the violations
   */
  private function getViolations(Throwable $exception): array
  {
    // Override in subclasses for validation exceptions
    return [];
  }

  /**
   * Method isFromDomainException.
   *
   * Checks if a FlattenException originated from a domain exception.
   *
   * @since 1.0.0
   *
   * @param FlattenException $exception the exception
   *
   * @return bool whether it's from domain
   */
  private function isFromDomainException(FlattenException $exception): bool
  {
    $class = $exception->getClass();

    return str_contains($class, '\\Domain\\Exception\\');
  }

  /**
   * Method toSnakeCase.
   *
   * Converts a string to snake_case.
   *
   * @since 1.0.0
   *
   * @param string $value the value
   *
   * @return string the snake_case value
   */
  private function toSnakeCase(string $value): string
  {
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value);
  }
  // #endregion
}
