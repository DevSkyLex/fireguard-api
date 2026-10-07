<?php

declare(strict_types=1);

namespace MaintenanceCost\Domain\Exception;

use RuntimeException;

/** Class MaintenanceCostException. Stable refusals of private maintenance finance. @category Exception */
final class MaintenanceCostException extends RuntimeException
{
  public function __construct(public readonly string $reason, string $message)
  {
    parent::__construct($message);
  }

  public static function invalid(string $message): self
  {
    return new self('maintenance_cost_invalid', $message);
  }

  public static function notFound(): self
  {
    return new self('maintenance_cost_not_found', 'Maintenance cost resource not found.');
  }

  public static function denied(): self
  {
    return new self('maintenance_cost_access_denied', 'Missing maintenance cost permission.');
  }

  public static function conflict(string $message): self
  {
    return new self('maintenance_cost_conflict', $message);
  }

  public static function preconditionRequired(): self
  {
    return new self('maintenance_cost_precondition_required', 'The planning revision is required.');
  }

  public static function stale(): self
  {
    return new self('maintenance_cost_revision_stale', 'The planning revision is stale.');
  }
}
