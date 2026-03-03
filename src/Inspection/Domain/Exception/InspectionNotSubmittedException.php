<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

final class InspectionNotSubmittedException extends RuntimeException
{
  public static function withId(string $id): self
  {
    return new self(sprintf('Inspection with ID "%s" must be submitted before it can be closed.', $id));
  }
}
