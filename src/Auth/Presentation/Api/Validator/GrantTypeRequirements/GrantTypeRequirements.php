<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Validator\GrantTypeRequirements;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint GrantTypeRequirements
 * @final
 *
 * Validates that required fields are present based on the OAuth 2.1 grant_type.
 * Each grant type has specific field requirements:
 * - refresh_token: requires refresh_token
 * - authorization_code: requires code and redirect_uri
 * - client_credentials: no additional fields required
 *
 * Note: PASSWORD and IMPLICIT grants are not supported (deprecated in OAuth 2.1).
 *
 * @category Validator
 * @package Auth\Presentation\Api\Validator\GrantTypeRequirements
 * @version 2.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/draft-ietf-oauth-v2-1-07
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class GrantTypeRequirements extends Constraint
{
  //#region Constants
  /**
   * Constant MESSAGE_REFRESH_TOKEN_REQUIRED
   *
   * Error message when
   * refresh_token is required.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public const string MESSAGE_REFRESH_TOKEN_REQUIRED = 'The refresh_token field is required for refresh_token grant.';

  /**
   * Constant MESSAGE_CODE_REQUIRED
   *
   * Error message when
   * code is required.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public const string MESSAGE_CODE_REQUIRED = 'The code field is required for authorization_code grant.';

  /**
   * Constant MESSAGE_REDIRECT_URI_REQUIRED
   *
   * Error message when
   * redirect_uri is required.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string
   */
  public const string MESSAGE_REDIRECT_URI_REQUIRED = 'The redirect_uri field is required for authorization_code grant.';

  /**
   * Constant MESSAGE_CODE_VERIFIER_REQUIRED
   *
   * Error message when code_verifier is required (PKCE).
   * OAuth 2.1 requires PKCE for all authorization_code grants.
   *
   * @access public
   * @since 2.0.0
   *
   * @var string
   */
  public const string MESSAGE_CODE_VERIFIER_REQUIRED = 'The code_verifier field is required for authorization_code grant (PKCE is mandatory in OAuth 2.1).';
  //#endregion

  //#region Methods
  /**
   * Method getTargets
   *
   * Returns the target of this constraint.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The target.
   */
  public function getTargets(): string
  {
    return self::CLASS_CONSTRAINT;
  }
  //#endregion
}
