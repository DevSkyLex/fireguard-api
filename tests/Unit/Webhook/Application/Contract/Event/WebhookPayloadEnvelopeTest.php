<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\Contract\Event;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Contract\Event\WebhookPayloadEnvelope;

/**
 * Test WebhookPayloadEnvelope.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookPayloadEnvelope::class)]
final class WebhookPayloadEnvelopeTest extends TestCase
{
  #[Test]
  public function itExposesItsReadonlyProperties(): void
  {
    $envelope = new WebhookPayloadEnvelope(
      id: 'delivery-1',
      type: 'intervention.published',
      created: '2026-01-01T00:00:00+00:00',
      organizationId: 'org-1',
      data: ['interventionId' => 'iv-1'],
    );

    self::assertSame('delivery-1', $envelope->id);
    self::assertSame('intervention.published', $envelope->type);
    self::assertSame('2026-01-01T00:00:00+00:00', $envelope->created);
    self::assertSame('org-1', $envelope->organizationId);
    self::assertSame(['interventionId' => 'iv-1'], $envelope->data);
  }

  #[Test]
  public function toArrayRendersTheEnvelopeInStableKeyOrder(): void
  {
    $envelope = new WebhookPayloadEnvelope(
      id: 'delivery-1',
      type: 'intervention.published',
      created: '2026-01-01T00:00:00+00:00',
      organizationId: 'org-1',
      data: ['interventionId' => 'iv-1'],
    );

    self::assertSame(
      [
        'id' => 'delivery-1',
        'type' => 'intervention.published',
        'created' => '2026-01-01T00:00:00+00:00',
        'organizationId' => 'org-1',
        'data' => ['interventionId' => 'iv-1'],
      ],
      $envelope->toArray(),
    );
  }
}
