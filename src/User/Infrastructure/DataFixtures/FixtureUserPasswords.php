<?php

declare(strict_types=1);

namespace User\Infrastructure\DataFixtures;

use InvalidArgumentException;
use JsonException;

use function array_keys;
use function array_unique;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function sort;
use function str_contains;
use function strlen;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Validated credentials for the five groups of seeded users.
 *
 * @category DataFixtures
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FixtureUserPasswords
{
  private function __construct(
    public string $admin,
    public string $test,
    public string $demo,
    public string $staff,
    public string $devClient,
  ) {
  }

  /**
   * Parses the complete fixture credential set without exposing its values in errors.
   *
   * @since 1.0.0
   *
   * @param string $json JSON object supplied by the fixture-loading environment
   * @param int $minimumLength minimum length in bytes for each password
   *
   * @return self the validated credentials
   */
  public static function fromJson(string $json, int $minimumLength = 1): self
  {
    try {
      $values = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      throw new InvalidArgumentException('Fixture user passwords must be a valid JSON object.');
    }

    if (!is_array($values)) {
      throw new InvalidArgumentException('Fixture user passwords must be a JSON object.');
    }

    $keys = array_keys($values);
    sort($keys);
    if (['admin', 'demo', 'dev_client', 'staff', 'test'] !== $keys) {
      throw new InvalidArgumentException('Fixture user passwords must define exactly admin, demo, dev_client, staff and test.');
    }

    foreach ($values as $value) {
      if (!is_string($value) || strlen($value) < $minimumLength || strlen($value) > 72 || str_contains($value, "\0") || '' === trim($value)) {
        throw new InvalidArgumentException('Each fixture user password must contain the configured minimum and at most 72 bytes without NUL.');
      }
    }

    if (count(array_unique($values)) !== count($values)) {
      throw new InvalidArgumentException('Fixture user passwords must be distinct between account groups.');
    }

    /** @var array{admin: string, test: string, demo: string, staff: string, dev_client: string} $values */
    return new self(
      admin: $values['admin'],
      test: $values['test'],
      demo: $values['demo'],
      staff: $values['staff'],
      devClient: $values['dev_client'],
    );
  }
}
