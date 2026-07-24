<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Validator\ValidAssistantModel;

use Assistant\Domain\Service\AssistantModelPolicy;
use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function is_string;

/**
 * Validator ValidAssistantModelValidator.
 *
 * Delegates to {@see AssistantModelPolicy::isAllowed()} so the API contract
 * enforces the exact same `OLLAMA_ALLOWED_MODELS` allowlist rule as the
 * domain — this is UX-only input-time validation;
 * `StartAssistantThreadHandler` re-runs the same policy as the authoritative
 * business-rule gate (mirrors
 * `Webhook\Presentation\Api\Validator\ValidWebhookUrl\ValidWebhookUrlValidator`).
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidAssistantModelValidator extends ConstraintValidator
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantModelPolicy $modelPolicy the tenant model allowlist policy
   */
  public function __construct(
    private readonly AssistantModelPolicy $modelPolicy,
  ) {
  }
  // #endregion

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
    if (!$constraint instanceof ValidAssistantModel) {
      throw new UnexpectedTypeException($constraint, ValidAssistantModel::class);
    }

    if (null === $value || '' === $value) {
      return;
    }

    if (!is_string($value)) {
      return;
    }

    if (!$this->modelPolicy->isAllowed($value)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('{{ value }}', $value)
        ->addViolation();
    }
  }
  // #endregion
}
