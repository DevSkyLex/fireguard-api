<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Validator\ValidWebhookEventType;

use Symfony\Component\Validator\{Constraint, ConstraintValidator};
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webhook\Application\Port\Outbound\WebhookEventCatalogPort;

use function is_string;

/**
 * Class ValidWebhookEventTypeValidator
 *
 * Validates selection against owner contributions before invoking a write use case.
 *
 * @category Validator
 */
final class ValidWebhookEventTypeValidator extends ConstraintValidator
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param WebhookEventCatalogPort $eventCatalog the installed subscribable catalog
   */
  public function __construct(private readonly WebhookEventCatalogPort $eventCatalog)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method validate
   *
   * @access public
   *
   * @param mixed $value the requested public event type
   * @param Constraint $constraint the expected event-type constraint
   *
   * @return void
   */
  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof ValidWebhookEventType) {
      throw new UnexpectedTypeException($constraint, ValidWebhookEventType::class);
    }
    if (!is_string($value) || !$this->eventCatalog->isAllowedEventType($value)) {
      $this->context->buildViolation($constraint->message)->addViolation();
    }
  }
  // #endregion
}
