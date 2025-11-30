<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Validator\GrantTypeRequirements;

use Auth\Presentation\Api\Dto\TokenInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator GrantTypeRequirementsValidator
 * @final
 *
 * Validates TokenInput based on the OAuth 2.1 grant_type.
 * Ensures that all required fields for a specific grant type are present.
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
final class GrantTypeRequirementsValidator extends ConstraintValidator
{
  //#region Methods
  /**
   * Method validate
   *
   * Validates the TokenInput object.
   *
   * @access public
   *
   * @param mixed $value The value to validate.
   * @param Constraint $constraint The constraint.
   *
   * @return void
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof GrantTypeRequirements) {
      throw new UnexpectedTypeException($constraint, GrantTypeRequirements::class);
    }

    if ($value === null) {
      return;
    }

    if (!$value instanceof TokenInput) {
      throw new UnexpectedValueException($value, TokenInput::class);
    }

    $grantType = $value->grantType;

    if ($grantType === null) {
      return; // Let NotBlank handle this
    }

    match ($grantType) {
      'refresh_token' => $this->validateRefreshTokenGrant($value, $constraint),
      'authorization_code' => $this->validateAuthorizationCodeGrant($value, $constraint),
      default => null, // client_credentials doesn't need extra validation
    };
  }

  /**
   * Method validateRefreshTokenGrant
   *
   * Validates fields required for refresh_token grant.
   *
   * @access private
   *
   * @param TokenInput $input The input.
   * @param GrantTypeRequirements $constraint The constraint.
   *
   * @return void
   */
  private function validateRefreshTokenGrant(TokenInput $input, GrantTypeRequirements $constraint): void
  {
    if (empty($input->refreshToken)) {
      $this->context->buildViolation($constraint::MESSAGE_REFRESH_TOKEN_REQUIRED)
        ->atPath('refreshToken')
        ->addViolation();
    }
  }

  /**
   * Method validateAuthorizationCodeGrant
   *
   * Validates fields required for authorization_code grant.
   * OAuth 2.1 requires PKCE (code_verifier) for all authorization_code grants.
   *
   * @access private
   *
   * @param TokenInput $input The input.
   * @param GrantTypeRequirements $constraint The constraint.
   *
   * @return void
   */
  private function validateAuthorizationCodeGrant(TokenInput $input, GrantTypeRequirements $constraint): void
  {
    if (empty($input->code)) {
      $this->context->buildViolation($constraint::MESSAGE_CODE_REQUIRED)
        ->atPath('code')
        ->addViolation();
    }

    if (empty($input->redirectUri)) {
      $this->context->buildViolation($constraint::MESSAGE_REDIRECT_URI_REQUIRED)
        ->atPath('redirectUri')
        ->addViolation();
    }

    // PKCE is mandatory in OAuth 2.1
    if (empty($input->codeVerifier)) {
      $this->context->buildViolation($constraint::MESSAGE_CODE_VERIFIER_REQUIRED)
        ->atPath('codeVerifier')
        ->addViolation();
    }
  }
  //#endregion
}
