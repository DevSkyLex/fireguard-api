<?php

declare(strict_types=1);

namespace Audit\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

final class AuditEventNotFoundException extends EntityNotFoundException
{
  public static function withId(string $id): self
  {
    return new self('AuditEvent with ID "' . $id . '" not found.');
  }
}
