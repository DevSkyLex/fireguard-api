<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Validator\ValidDuration;

use DateInterval;
use Exception;
use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function is_string;

/**
 * Validator ValidDurationValidator.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidDurationValidator extends ConstraintValidator
{
  // #region Methods
  /**
   * Method validate
   * {@inheritDoc}.
   *
   * Validates ValidDuration constraint.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value to validate
   * @param Constraint $constraint the constraint to validate against
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ValidDuration) {
      throw new UnexpectedTypeException(
        value: $constraint,
        expectedType: ValidDuration::class,
      );
    }

    if (null === $value || '' === $value) {
      return;
    }

    if (!is_string($value)) {
      return;
    }

    try {
      new DateInterval($value);
    } catch (Exception) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('{{ duration }}', $value)
        ->addViolation();
    }
  }
  // #endregion
}
