<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator ValidScopesValidator
 * @final
 *
 * Validates ValidScopes constraint.
 *
 * @category Validator
 * @package Shared\Presentation\Api\Validator
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
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ValidScopes) {
      throw new UnexpectedTypeException(value: $constraint, expectedType: ValidScopes::class);
    }

    if ($value === null) {
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
  //#endregion
}
