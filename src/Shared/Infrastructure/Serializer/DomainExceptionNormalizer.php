<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Serializer;

use Shared\Domain\Exception\DomainException;
use Shared\Domain\Exception\EntityNotFoundException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizer DomainExceptionNormalizer
 * @final
 *
 * Normalizes domain exceptions for API responses.
 * Provides structured error responses following RFC 7807 Problem Details.
 *
 * @category Normalizer
 * @package Shared\Infrastructure\Serializer
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DomainExceptionNormalizer implements NormalizerInterface
{
  //#region Methods
  /**
   * Method normalize
   * {@inheritDoc}
   *
   * @param mixed $object The exception to normalize.
   * @param string|null $format The format.
   * @param array<string, mixed> $context The context.
   *
   * @return array<string, mixed> The normalized data.
   */
  public function normalize(mixed $object, ?string $format = null, array $context = []): array
  {
    if (!$object instanceof \Throwable) {
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
   * @param mixed $data The data.
   * @param string|null $format The format.
   * @param array<string, mixed> $context The context.
   *
   * @return bool Whether normalization is supported.
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
   * @param string|null $format The format.
   *
   * @return array<class-string|'*', bool|null> The supported types.
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
   * Method getStatusCode
   *
   * Gets the HTTP status code for the exception.
   *
   * @access private
   * @since 1.0.0
   *
   * @param \Throwable $exception The exception.
   *
   * @return int The status code.
   */
  private function getStatusCode(\Throwable $exception): int
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
   * Method getTitle
   *
   * Gets the error title.
   *
   * @access private
   * @since 1.0.0
   *
   * @param \Throwable $exception The exception.
   *
   * @return string The title.
   */
  private function getTitle(\Throwable $exception): string
  {
    if ($exception instanceof EntityNotFoundException) {
      return 'Resource Not Found';
    }

    return 'Domain Error';
  }

  /**
   * Method getType
   *
   * Gets the error type URI.
   *
   * @access private
   * @since 1.0.0
   *
   * @param \Throwable $exception The exception.
   *
   * @return string The type URI.
   */
  private function getType(\Throwable $exception): string
  {
    $className = $exception::class;
    $shortName = substr($className, strrpos($className, '\\') + 1);

    return '/errors/' . $this->toSnakeCase($shortName);
  }

  /**
   * Method getViolations
   *
   * Gets any constraint violations.
   *
   * @access private
   * @since 1.0.0
   *
   * @param \Throwable $exception The exception.
   *
   * @return list<array<string, string>> The violations.
   */
  private function getViolations(\Throwable $exception): array
  {
    // Override in subclasses for validation exceptions
    return [];
  }

  /**
   * Method isFromDomainException
   *
   * Checks if a FlattenException originated from a domain exception.
   *
   * @access private
   * @since 1.0.0
   *
   * @param FlattenException $exception The exception.
   *
   * @return bool Whether it's from domain.
   */
  private function isFromDomainException(FlattenException $exception): bool
  {
    $class = $exception->getClass();
    return str_contains($class, '\\Domain\\Exception\\');
  }

  /**
   * Method toSnakeCase
   *
   * Converts a string to snake_case.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $value The value.
   *
   * @return string The snake_case value.
   */
  private function toSnakeCase(string $value): string
  {
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value);
  }
  //#endregion
}
