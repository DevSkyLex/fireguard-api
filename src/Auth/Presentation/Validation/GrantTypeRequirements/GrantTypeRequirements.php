<?php

declare(strict_types=1);

namespace Auth\Presentation\Validation\GrantTypeRequirements;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint GrantTypeRequirements
 * @final
 *
 * Validates that required fields are present based on the OAuth 2.1 grant_type.
 *
 * @category Validation
 * @package Auth\Presentation\Validation\GrantTypeRequirements
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class GrantTypeRequirements extends Constraint
{
  public const string MESSAGE_REFRESH_TOKEN_REQUIRED = 'The refresh_token field is required for refresh_token grant.';
  public const string MESSAGE_CODE_REQUIRED = 'The code field is required for authorization_code grant.';
  public const string MESSAGE_REDIRECT_URI_REQUIRED = 'The redirect_uri field is required for authorization_code grant.';
  public const string MESSAGE_CODE_VERIFIER_REQUIRED = 'The code_verifier field is required for authorization_code grant (PKCE is mandatory in OAuth 2.1).';

  public function getTargets(): string
  {
    return self::CLASS_CONSTRAINT;
  }
}
