<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\Exception;

use RuntimeException;

/**
 * Stable refusals for organization-owned maintenance requests.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ServiceRequestException extends RuntimeException
{
  public function __construct(public readonly string $reason, string $message)
  {
    parent::__construct($message);
  }

  public static function invalid(string $message): self
  {
    return new self('service_request_invalid', $message);
  }

  public static function notFound(): self
  {
    return new self('service_request_not_found', 'Service request not found.');
  }

  public static function denied(): self
  {
    return new self('service_request_access_denied', 'Missing service request permission.');
  }

  public static function stale(): self
  {
    return new self('service_request_revision_stale', 'The service request revision is stale.');
  }

  public static function transitionConflict(string $message = 'This service request cannot perform this transition.'): self
  {
    return new self('service_request_transition_conflict', $message);
  }

  public static function operationConflict(string $message = 'This conversion operation already belongs to another request or payload.'): self
  {
    return new self('service_request_operation_conflict', $message);
  }
}
