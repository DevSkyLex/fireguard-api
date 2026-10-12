<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Event;

use DateTimeImmutable;

/**
 * Class WebhookEventProjection
 *
 * Carries the curated public payload without exposing the source owner's private types.
 *
 * @category Contract
 */
final readonly class WebhookEventProjection
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param string $organizationId the source organization
   * @param WebhookEventType $type the stable public identifier
   * @param array<string, mixed> $data the explicitly selected public payload
   * @param DateTimeImmutable $occurredAt the original occurrence time
   */
  public function __construct(public string $organizationId, public WebhookEventType $type, public array $data, public DateTimeImmutable $occurredAt)
  {
  }
  // #endregion
}
