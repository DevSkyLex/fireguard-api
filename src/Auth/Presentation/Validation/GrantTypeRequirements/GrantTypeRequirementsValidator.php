<?php

declare(strict_types=1);

namespace Auth\Presentation\Validation\GrantTypeRequirements;

use Auth\Presentation\Dto\Request\TokenInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator GrantTypeRequirementsValidator
 * @final
 *
 * Validates TokenInput based on the OAuth 2.1 grant_type.
 *
 * @category Validation
 * @package Auth\Presentation\Validation\GrantTypeRequirements
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GrantTypeRequirementsValidator extends ConstraintValidator
{
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
      return;
    }

    match ($grantType) {
      'refresh_token' => $this->validateRefreshTokenGrant($value, $constraint),
      'authorization_code' => $this->validateAuthorizationCodeGrant($value, $constraint),
      default => null,
    };
  }

  private function validateRefreshTokenGrant(TokenInput $input, GrantTypeRequirements $constraint): void
  {
    if (empty($input->refreshToken)) {
      $this->context->buildViolation($constraint::MESSAGE_REFRESH_TOKEN_REQUIRED)
        ->atPath('refreshToken')
        ->addViolation();
    }
  }

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
}
