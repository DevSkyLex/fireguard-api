<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use InvalidArgumentException;

use function mb_strlen;
use function sprintf;
use function trim;

/**
 * ValueObject Inspector.
 *
 * Represents the person or entity who performed an inspection.
 * Supports both system users (USER) and external parties (EXTERNAL).
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Inspector
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectorType $type the inspector type
   * @param string $name the inspector display name
   * @param ?string $userId the optional user identifier (required for USER type)
   * @param ?string $organizationName the optional external organization name
   */
  private function __construct(
    public InspectorType $type,
    public string $name,
    public ?string $userId = null,
    public ?string $organizationName = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method forUser.
   *
   * Creates an inspector representing a system user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $name the user display name
   *
   * @return self the inspector value object
   */
  public static function forUser(string $userId, string $name): self
  {
    $normalized = trim($name);
    if ('' === $normalized) {
      throw new InvalidArgumentException('Inspector name must not be empty.');
    }

    if (mb_strlen($normalized) > 255) {
      throw new InvalidArgumentException(
        sprintf('Inspector name must be at most %d characters.', 255),
      );
    }

    $userId = trim($userId);
    if ('' === $userId) {
      throw new InvalidArgumentException('User ID must not be empty for USER inspector type.');
    }

    return new self(
      type: InspectorType::USER,
      name: $normalized,
      userId: $userId,
      organizationName: null,
    );
  }

  /**
   * Method forExternal.
   *
   * Creates an inspector representing an external party.
   *
   * @since 1.0.0
   *
   * @param string $name the external inspector name
   * @param ?string $organizationName the optional external organization name
   *
   * @return self the inspector value object
   */
  public static function forExternal(string $name, ?string $organizationName = null): self
  {
    $normalized = trim($name);
    if ('' === $normalized) {
      throw new InvalidArgumentException('Inspector name must not be empty.');
    }

    if (mb_strlen($normalized) > 255) {
      throw new InvalidArgumentException(
        sprintf('Inspector name must be at most %d characters.', 255),
      );
    }

    $normalizedOrg = null;
    if (null !== $organizationName) {
      $normalizedOrg = trim($organizationName);
      if ('' === $normalizedOrg) {
        $normalizedOrg = null;
      } elseif (mb_strlen($normalizedOrg) > 255) {
        throw new InvalidArgumentException(
          sprintf('Inspector organization name must be at most %d characters.', 255),
        );
      }
    }

    return new self(
      type: InspectorType::EXTERNAL,
      name: $normalized,
      userId: null,
      organizationName: $normalizedOrg,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an inspector from persisted state.
   *
   * @since 1.0.0
   *
   * @param InspectorType $type the inspector type
   * @param string $name the inspector display name
   * @param ?string $userId the optional user identifier
   * @param ?string $organizationName the optional external organization name
   *
   * @return self the inspector value object
   */
  public static function reconstitute(
    InspectorType $type,
    string $name,
    ?string $userId = null,
    ?string $organizationName = null,
  ): self {
    return new self(
      type: $type,
      name: $name,
      userId: $userId,
      organizationName: $organizationName,
    );
  }
  // #endregion
}
