<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function iconv;
use function mb_strlen;
use function preg_match;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * ValueObject OrganizationSlug.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSlug implements Stringable
{
  private const int MIN_LENGTH = 3;

  private const int MAX_LENGTH = 120;

  private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationSlug class.
   *
   * @since 1.0.0
   *
   * @param string $value the slug value
   */
  public function __construct(string $value)
  {
    $normalized = trim(strtolower($value));
    $length = mb_strlen($normalized);

    if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
      throw InvalidValueException::because('Organization slug must be between 3 and 120 characters.');
    }

    if (!preg_match(self::PATTERN, $normalized)) {
      throw InvalidValueException::because('Organization slug must contain lowercase letters, numbers and single dashes.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized slug value.
   *
   * @since 1.0.0
   *
   * @return string the slug value
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method fromName.
   *
   * Builds a slug from a free-text organization name.
   *
   * @since 1.0.0
   *
   * @param string $name the organization name
   *
   * @return self the generated slug
   */
  public static function fromName(string $name): self
  {
    $normalized = trim($name);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

    if (false !== $ascii) {
      $normalized = $ascii;
    }

    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    if ('' === $normalized) {
      $normalized = 'organization';
    }

    if (mb_strlen($normalized) > self::MAX_LENGTH) {
      $normalized = trim((string) preg_replace('/-+$/', '', (string) preg_replace('/^(.{1,120}).*$/', '$1', $normalized)), '-');
    }

    return new self($normalized);
  }

  /**
   * Method equals.
   *
   * Checks whether two slugs are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the slug to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
