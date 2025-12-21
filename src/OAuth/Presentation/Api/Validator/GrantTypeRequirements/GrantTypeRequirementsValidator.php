<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\GrantTypeRequirements;

use OAuth\Presentation\Api\Dto\Input\TokenInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator GrantTypeRequirementsValidator.
 *
 * @category Validation
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GrantTypeRequirementsValidator extends ConstraintValidator
{
  // #region Methods
  /**
   * Method validate
   * {@inheritDoc}
   *
   * Validates TokenInput based on the OAuth 2.1
   * grant_type.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value to validate
   * @param Constraint $constraint the constraint to validate against
   *
   * @return void no return value
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof GrantTypeRequirements) {
      throw new UnexpectedTypeException(
        value: $constraint,
        expectedType: GrantTypeRequirements::class,
      );
    }

    if (null === $value) {
      return;
    }

    if (!$value instanceof TokenInput) {
      throw new UnexpectedValueException(
        value: $value,
        expectedType: TokenInput::class,
      );
    }

    $grantType = $value->grantType;

    if (null === $grantType) {
      return;
    }

    match ($grantType) {
      'refresh_token' => $this->validateRefreshTokenGrant($value, $constraint),
      'authorization_code' => $this->validateAuthorizationCodeGrant($value, $constraint),
      default => null,
    };
  }

  /**
   * Method validateRefreshTokenGrant.
   *
   * Validates the refresh token grant type.
   *
   * @since 1.0.0
   *
   * @param TokenInput $input the input to validate
   * @param GrantTypeRequirements $constraint the constraint to validate against
   *
   * @return void no return value
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
   * Method validateAuthorizationCodeGrant.
   *
   * Validates the authorization
   * code grant type.
   *
   * @since 1.0.0
   *
   * @param TokenInput $input the input to validate
   * @param GrantTypeRequirements $constraint the constraint to validate against
   *
   * @return void no return value
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

    if (empty($input->codeVerifier)) {
      $this->context->buildViolation($constraint::MESSAGE_CODE_VERIFIER_REQUIRED)
        ->atPath('codeVerifier')
        ->addViolation();
    }
  }
  // #endregion
}
