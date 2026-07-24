<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Event;

/**
 * Contract WebhookPayloadEnvelope.
 *
 * The stable public JSON envelope every webhook delivery carries — never
 * the raw internal domain event object. `data` is built by a dedicated,
 * per-event-type payload builder ({@see WebhookEventCatalog::buildPayload()})
 * that reads the typed domain event and emits only public, PII-free
 * fields, which is what keeps this contract stable against internal
 * refactors.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WebhookPayloadEnvelope
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the delivery identifier (also the consumer idempotency key)
   * @param string $type the public event type ({@see WebhookEventType})
   * @param string $created the ISO-8601 timestamp the source event occurred at
   * @param string $organizationId the owning organization identifier
   * @param array<string, mixed> $data the curated, PII-free event data
   */
  public function __construct(
    public string $id,
    public string $type,
    public string $created,
    public string $organizationId,
    public array $data,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method toArray.
   *
   * Renders the envelope as a plain array, in the exact key order the
   * signed request body serializes it with.
   *
   * @since 1.0.0
   *
   * @return array{id: string, type: string, created: string, organizationId: string, data: array<string, mixed>}
   */
  public function toArray(): array
  {
    return [
      'id' => $this->id,
      'type' => $this->type,
      'created' => $this->created,
      'organizationId' => $this->organizationId,
      'data' => $this->data,
    ];
  }
  // #endregion
}
