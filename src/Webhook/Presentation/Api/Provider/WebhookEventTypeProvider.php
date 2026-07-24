<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Webhook\Application\Contract\Event\WebhookEventCatalog;
use Webhook\Presentation\Api\Dto\Output\WebhookEventTypeOutput;

use function array_map;
use function str_replace;
use function ucwords;

/**
 * Provider WebhookEventTypeProvider.
 *
 * Static reference catalog (`GET /webhooks/event-types`) — the curated
 * allowlist a subscription may register for. Kept thin: it reads directly
 * from `WebhookEventCatalog`, a static registry, per ARCHITECTURE.md's
 * reference-catalog guideline.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<WebhookEventTypeOutput>
 */
final readonly class WebhookEventTypeProvider implements ProviderInterface
{
  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return list<WebhookEventTypeOutput> the reference catalog rows
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    return array_map(self::toOutput(...), WebhookEventCatalog::allowedEventTypes());
  }

  /**
   * Method toOutput.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $value the public event type value
   *
   * @return WebhookEventTypeOutput the mapped output
   */
  private static function toOutput(string $value): WebhookEventTypeOutput
  {
    $output = new WebhookEventTypeOutput();
    $output->value = $value;
    $output->label = ucwords(str_replace(['.', '_'], ' ', $value));

    return $output;
  }
  // #endregion
}
