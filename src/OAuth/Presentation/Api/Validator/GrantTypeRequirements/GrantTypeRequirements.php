<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\GrantTypeRequirements;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint GrantTypeRequirements.
 *
 * @category Validation
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class GrantTypeRequirements extends Constraint
{
  // #region Constants
  /**
   * Constant MESSAGE_REFRESH_TOKEN_REQUIRED.
   *
   * The message to display when the refresh_token field
   * is required for refresh_token grant.
   *
   * @since 2.0.0
   *
   * @var string
   */
  public const string MESSAGE_REFRESH_TOKEN_REQUIRED = 'The refresh_token field is required for refresh_token grant.';

  /**
   * Constant MESSAGE_CODE_REQUIRED.
   *
   * The message to display when the code field
   * is required for authorization_code grant.
   *
   * @since 2.0.0
   *
   * @var string
   */
  public const string MESSAGE_CODE_REQUIRED = 'The code field is required for authorization_code grant.';

  /**
   * Constant MESSAGE_REDIRECT_URI_REQUIRED.
   *
   * The message to display when the redirect_uri field
   * is required for authorization_code grant.
   *
   * @since 2.0.0
   *
   * @var string
   */
  public const string MESSAGE_REDIRECT_URI_REQUIRED = 'The redirect_uri field is required for authorization_code grant.';

  /**
   * Constant MESSAGE_CODE_VERIFIER_REQUIRED.
   *
   * The message to display when the code_verifier field
   * is required for authorization_code grant (PKCE is mandatory in OAuth 2.1).
   *
   * @since 2.0.0
   *
   * @var string
   */
  public const string MESSAGE_CODE_VERIFIER_REQUIRED = 'The code_verifier field is required for authorization_code grant (PKCE is mandatory in OAuth 2.1).';
  // #endregion

  // #region Methods
  /**
   * Method getTargets
   * {@inheritDoc}
   *
   * Gets the targets
   *
   * @since 2.0.0
   *
   * @return string the target
   */
  public function getTargets(): string
  {
    return self::CLASS_CONSTRAINT;
  }
  // #endregion
}
