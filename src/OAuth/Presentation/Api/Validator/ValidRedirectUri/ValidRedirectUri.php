<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\ValidRedirectUri;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidRedirectUri
 * @final
 *
 * Validates that redirect URIs are valid for OAuth2.
 *
 * @category Validator
 * @package OAuth\Presentation\Api\Validator
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ValidRedirectUri extends Constraint
{
  //#region Properties
  /**
   * Property messageInvalid
   *
   * @var string
   */
  public string $messageInvalid = 'The redirect URI "{{ uri }}" is not a valid URL.';

  /**
   * Property messageScheme
   *
   * @var string
   */
  public string $messageScheme = 'The redirect URI "{{ uri }}" must use HTTPS (except for localhost).';

  /**
   * Property messageFragment
   *
   * @var string
   */
  public string $messageFragment = 'The redirect URI "{{ uri }}" must not contain a fragment.';

  /**
   * Property allowLocalhost
   *
   * Whether to allow HTTP for localhost.
   *
   * @var bool
   */
  public bool $allowLocalhost = true;
  //#endregion
}
