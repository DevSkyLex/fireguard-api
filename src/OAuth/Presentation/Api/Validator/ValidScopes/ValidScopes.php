<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\ValidScopes;

use Attribute;
use Symfony\Component\Validator\Constraint;

use function array_is_list;
use function is_array;
use function is_string;

/**
 * Constraint ValidScopes.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidScopes extends Constraint
{
  // #region Properties
  /**
   * Property message.
   *
   * The error message.
   *
   * @since 1.0.0
   */
  public string $message = 'The scope "{{ scope }}" is not a valid OAuth2 scope.';

  /**
   * Property allowedScopes.
   *
   * The allowed scopes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $allowedScopes = [
    'openid',
    'profile',
    'email',
    'address',
    'phone',
    'offline_access',
    'read',
    'write',
    'admin',
  ];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ValidScopes class.
   *
   * @since 1.0.0
   *
   * @param list<string>|null $allowedScopes the allowed scopes
   * @param string|null $message the error message
   * @param array<string, mixed>|null $options additional options
   * @param list<string>|null $groups validation groups
   * @param mixed $payload the payload
   */
  public function __construct(
    ?array $allowedScopes = null,
    ?string $message = null,
    ?array $options = null,
    ?array $groups = null,
    mixed $payload = null,
  ) {
    if (null !== $options) {
      $allowedScopesOption = $options['allowedScopes'] ?? null;
      if (self::isStringList($allowedScopesOption)) {
        $allowedScopes ??= $allowedScopesOption;
      }

      $message ??= isset($options['message']) && is_string($options['message'])
        ? $options['message']
        : null;

      $groupsOption = $options['groups'] ?? null;
      if (self::isStringList($groupsOption)) {
        $groups ??= $groupsOption;
      }

      $payload ??= $options['payload'] ?? null;
    }

    parent::__construct(
      options: null,
      groups: $groups,
      payload: $payload,
    );

    $this->allowedScopes = $allowedScopes ?? $this->allowedScopes;
    $this->message = $message ?? $this->message;
  }
  // #endregion

  /**
   * @phpstan-assert-if-true list<string> $value
   */
  private static function isStringList(mixed $value): bool
  {
    if (!is_array($value) || !array_is_list($value)) {
      return false;
    }

    foreach ($value as $item) {
      if (!is_string($item)) {
        return false;
      }
    }

    return true;
  }
}
