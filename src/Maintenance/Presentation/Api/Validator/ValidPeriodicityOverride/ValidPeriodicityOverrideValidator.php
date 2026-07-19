<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Validator\ValidPeriodicityOverride;

use Maintenance\Domain\ValueObject\PeriodicityInterval;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function is_string;

/**
 * Validator ValidPeriodicityOverrideValidator.
 *
 * Delegates to {@see PeriodicityInterval::fromString()} so the API contract
 * enforces the exact same bounds ([P28D, P10Y]) as the domain, without
 * duplicating the rule.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidPeriodicityOverrideValidator extends ConstraintValidator
{
  // #region Methods
  /**
   * Method validate.
   *
   * {@inheritDoc}
   *
   * @since 1.0.0
   *
   * @param mixed $value the value to validate
   * @param Constraint $constraint the constraint to validate against
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ValidPeriodicityOverride) {
      throw new UnexpectedTypeException($constraint, ValidPeriodicityOverride::class);
    }

    if (null === $value || '' === $value) {
      return;
    }

    if (!is_string($value)) {
      return;
    }

    try {
      PeriodicityInterval::fromString($value);
    } catch (InvalidValueException) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('{{ value }}', $value)
        ->addViolation();
    }
  }
  // #endregion
}
