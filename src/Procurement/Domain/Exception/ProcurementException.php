<?php

declare(strict_types=1);

namespace Procurement\Domain\Exception;

use RuntimeException;

/** Procurement failures retain business codes; HTTP mapping belongs to Presentation. */
final class ProcurementException extends RuntimeException
{
  private function __construct(public readonly string $errorCode, string $message)
  {
    parent::__construct($message);
  }

  public static function invalid(string $message): self
  {
    return new self('invalid', $message);
  }

  public static function conflict(string $message): self
  {
    return new self('conflict', $message);
  }

  public static function stale(string $message = 'The resource revision is stale.'): self
  {
    return new self('stale', $message);
  }

  public static function notFound(): self
  {
    return new self('not_found', 'Procurement resource not found.');
  }

  public static function denied(): self
  {
    return new self('denied', 'Procurement permission denied.');
  }

  public static function revisionRequired(): self
  {
    return new self('revision_required', 'A resource revision is required.');
  }
}
