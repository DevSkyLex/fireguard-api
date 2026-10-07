<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

/**
 * Enum OrganizationOperatingProfile.
 *
 * Describes operational defaults without granting organization permissions.
 *
 * @category ValueObject
 */
enum OrganizationOperatingProfile: string
{
  case OPERATOR = 'operator';
  case SERVICE_PROVIDER = 'service_provider';

  /**
   * Method fromString.
   *
   * @param string $value the requested operating profile
   *
   * @return self the validated profile
   */
  public static function fromString(string $value): self
  {
    return self::tryFrom($value) ?? throw new InvalidValueException('Unsupported organization operating profile.');
  }
}
