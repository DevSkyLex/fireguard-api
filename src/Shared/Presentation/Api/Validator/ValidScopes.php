<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidScopes
 * @final
 *
 * Validates that scopes are valid OAuth2 scopes.
 *
 * @category Validator
 * @package Shared\Presentation\Api\Validator
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ValidScopes extends Constraint
{
  //#region Properties
  /**
   * Property message
   *
   * The error message.
   *
   * @var string
   */
  public string $message = 'The scope "{{ scope }}" is not a valid OAuth2 scope.';

  /**
   * Property allowedScopes
   *
   * The allowed scopes.
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
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @param list<string>|null $allowedScopes The allowed scopes.
   * @param string|null $message The error message.
   * @param array<string, mixed>|null $options Additional options.
   * @param list<string>|null $groups Validation groups.
   * @param mixed $payload The payload.
   */
  public function __construct(
    ?array $allowedScopes = null,
    ?string $message = null,
    ?array $options = null,
    ?array $groups = null,
    mixed $payload = null
  ) {
    parent::__construct($options ?? [], $groups, $payload);

    $this->allowedScopes = $allowedScopes ?? $this->allowedScopes;
    $this->message = $message ?? $this->message;
  }
  //#endregion
}
