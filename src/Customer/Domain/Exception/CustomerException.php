<?php

declare(strict_types=1);

namespace Customer\Domain\Exception;

use RuntimeException;

/** Class CustomerException. Stable customer refusals. @category Exception */
final class CustomerException extends RuntimeException
{
  public function __construct(public readonly string $reason, string $message)
  {
    parent::__construct($message);
  }

  public static function notFound(): self
  {
    return new self('customer_not_found', 'Customer not found.');
  }

  public static function denied(): self
  {
    return new self('customer_access_denied', 'Missing customer permission.');
  }

  public static function invalid(string $message): self
  {
    return new self('customer_invalid', $message);
  }

  public static function stale(): self
  {
    return new self('customer_revision_stale', 'The customer revision is stale.');
  }

  public static function codeConflict(): self
  {
    return new self('customer_code_conflict', 'Customer code already exists in this organization.');
  }
}
