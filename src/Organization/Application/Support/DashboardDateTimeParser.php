<?php

declare(strict_types=1);

namespace Organization\Application\Support;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

use function preg_match;
use function sprintf;
use function str_pad;
use function substr;

final class DashboardDateTimeParser
{
  public static function parseNullable(?string $value, string $filterName): ?DateTimeImmutable
  {
    if (null === $value || '' === $value) {
      return null;
    }

    return self::parse($value, $filterName);
  }

  public static function parse(string $value, string $filterName): DateTimeImmutable
  {
    if (!preg_match(
      '/^(?<date>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?<fraction>\.\d{1,6})?(?<timezone>Z|[+-]\d{2}:\d{2})$/',
      $value,
      $matches,
    )) {
      throw InvalidValueException::because(sprintf(
        'Invalid "%s" datetime filter. Use an ISO 8601 datetime with an explicit timezone offset.',
        $filterName,
      ));
    }

    $fraction = $matches['fraction'];
    if ('' !== $fraction) {
      $fraction = '.' . str_pad(substr($fraction, 1), 6, '0');
    }

    $timeZone = $matches['timezone'];
    $normalizedValue = $matches['date'] . $fraction . ('Z' === $timeZone ? '+00:00' : $timeZone);
    $format = '' === $fraction ? '!Y-m-d\TH:i:sP' : '!Y-m-d\TH:i:s.uP';
    $dateTime = DateTimeImmutable::createFromFormat($format, $normalizedValue);

    $errors = DateTimeImmutable::getLastErrors();
    if (
      false === $dateTime
      || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))
    ) {
      throw InvalidValueException::because(sprintf(
        'Invalid "%s" datetime filter. Use an ISO 8601 datetime with an explicit timezone offset.',
        $filterName,
      ));
    }

    return $dateTime;
  }
}
