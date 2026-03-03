<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

final class InspectionAlreadySubmittedException extends RuntimeException
{
  public static function withId(string $id): self
  {
    return new self(sprintf('Inspection with ID "%s" is already submitted.', $id));
  }
}
