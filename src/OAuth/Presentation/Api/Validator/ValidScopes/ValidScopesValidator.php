<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\ValidScopes;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function is_array;
use function is_string;
use function in_array;

/**
 * Validator ValidScopesValidator
 * @final
 *
 * Validates ValidScopes constraint.
 *
 * @category Validator
 * @package OAuth\Presentation\Api\Validator
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidScopesValidator extends ConstraintValidator
{
  //#region Methods
  /**
   * Method validate
   * {@inheritDoc}
   * 
   * Validates ValidScopes constraint.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param mixed $value The value to validate.
   * @param Constraint $constraint The constraint to validate against.
   * 
   * @return void No return value.
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ValidScopes) {
      throw new UnexpectedTypeException(
        value: $constraint, 
        expectedType: ValidScopes::class
      );
    }

    if ($value === null) return;

    if (!is_array($value)) {
      $value = [$value];
    }

    foreach ($value as $scope) {
      if (!is_string($scope)) continue;

      if (!in_array($scope, $constraint->allowedScopes, true)) {
        $this->context->buildViolation(message: $constraint->message)
          ->setParameter('{{ scope }}', $scope)
          ->addViolation();
      }
    }
  }
  //#endregion
}
