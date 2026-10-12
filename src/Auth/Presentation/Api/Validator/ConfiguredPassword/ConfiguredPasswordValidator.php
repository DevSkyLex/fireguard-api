<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Validator\ConfiguredPassword;

use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use User\Application\Contract\Password\PasswordPolicyDescription;
use User\Application\Port\Outbound\PasswordPolicyPort;

use function implode;
use function is_string;

/**
 * Validator ConfiguredPasswordValidator.
 *
 * Preserves default validation copy and describes only the configured custom requirements.
 *
 * @category Validator
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConfiguredPasswordValidator extends ConstraintValidator
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the password requirements published by the User owner.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PasswordPolicyPort $policy published requirements owned by User
   *
   * @return void
   */
  public function __construct(private readonly PasswordPolicyPort $policy)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method validate
   *
   * Translates owned requirement identifiers into the existing localized validation messages.
   *
   * @access public
   *
   * @param mixed $value the submitted password, with empty and non-string values left to other constraints
   * @param Constraint $constraint the configured password constraint
   *
   * @return void
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ConfiguredPassword) {
      throw new UnexpectedTypeException($constraint, ConfiguredPassword::class);
    }
    if (null === $value || '' === $value || !is_string($value)) {
      return;
    }
    $rules = $this->policy->describe();
    foreach ($this->policy->violations($value) as $violation) {
      if ('minimum_length' === $violation) {
        $this->context->buildViolation('Password must be at least {{ limit }} characters long.')->setParameter('{{ limit }}', (string) $rules->minimumLength)->addViolation();
      } elseif ('maximum_length' === $violation) {
        $this->context->buildViolation('Password cannot exceed {{ limit }} characters.')->setParameter('{{ limit }}', (string) $rules->maximumLength)->addViolation();
      } else {
        $this->characterViolation($rules);
      }
    }
  }

  /**
   * Method characterViolation
   *
   * Retains the default translated copy while describing custom character requirements.
   *
   * @access private
   *
   * @param PasswordPolicyDescription $rules the requirements owned by User
   *
   * @return void
   */
  private function characterViolation(PasswordPolicyDescription $rules): void
  {
    if ($rules->requireUppercase && $rules->requireLowercase && $rules->requireDigit && '@$!%*?&#' === $rules->specialCharacters) {
      $this->context->buildViolation('Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.')->addViolation();

      return;
    }
    $requirements = [];
    if ($rules->requireUppercase) {
      $requirements[] = 'an uppercase letter';
    }
    if ($rules->requireLowercase) {
      $requirements[] = 'a lowercase letter';
    }
    if ($rules->requireDigit) {
      $requirements[] = 'a digit';
    }
    if ('' !== $rules->specialCharacters) {
      $requirements[] = 'a character from "' . $rules->specialCharacters . '"';
    }
    $this->context->buildViolation('Password must contain: {{ requirements }}.')->setParameter('{{ requirements }}', implode(', ', $requirements))->addViolation();
  }
  // #endregion
}
