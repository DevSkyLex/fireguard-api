<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Validator\ValidWebhookUrl;

use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webhook\Domain\Exception\WebhookValidationException;
use Webhook\Domain\Service\WebhookUrlPolicy;

use function is_string;

/**
 * Validator ValidWebhookUrlValidator.
 *
 * Delegates to {@see WebhookUrlPolicy::assertValidUrl()} so the API
 * contract enforces the exact same SSRF-hardening rule as the domain —
 * this is UX-only input-time validation; `CreateWebhookSubscriptionHandler`
 * and `UpdateWebhookSubscriptionHandler` re-run the same policy as the
 * authoritative business-rule gate.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidWebhookUrlValidator extends ConstraintValidator
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param WebhookUrlPolicy $urlPolicy the SSRF hardening URL policy
   */
  public function __construct(
    private readonly WebhookUrlPolicy $urlPolicy,
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
    if (!$constraint instanceof ValidWebhookUrl) {
      throw new UnexpectedTypeException($constraint, ValidWebhookUrl::class);
    }

    if (null === $value || '' === $value) {
      return;
    }

    if (!is_string($value)) {
      return;
    }

    try {
      $this->urlPolicy->assertValidUrl($value);
    } catch (WebhookValidationException) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('{{ value }}', $value)
        ->addViolation();
    }
  }
  // #endregion
}
