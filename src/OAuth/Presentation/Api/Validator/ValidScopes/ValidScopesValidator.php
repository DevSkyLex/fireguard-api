<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Validator\ValidScopes;

use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function in_array;
use function is_array;
use function is_string;

/**
 * Validator ValidScopesValidator.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidScopesValidator extends ConstraintValidator
{
  // #region Methods
  /**
   * Method validate
   * {@inheritDoc}
   *
   * Validates ValidScopes constraint.
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
    if (!$constraint instanceof ValidScopes) {
      throw new UnexpectedTypeException(
        value: $constraint,
        expectedType: ValidScopes::class,
      );
    }

    if (null === $value) {
      return;
    }

    if (!is_array($value)) {
      $value = [$value];
    }

    foreach ($value as $scope) {
      if (!is_string($scope)) {
        continue;
      }

      if (!in_array($scope, $constraint->allowedScopes, true)) {
        $this->context->buildViolation(message: $constraint->message)
          ->setParameter('{{ scope }}', $scope)
          ->addViolation();
      }
    }
  }
  // #endregion
}
