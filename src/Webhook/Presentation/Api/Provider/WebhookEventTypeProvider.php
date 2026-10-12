<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Webhook\Application\Contract\Event\WebhookEventDescriptor;
use Webhook\Application\Port\Outbound\WebhookEventCatalogPort;
use Webhook\Presentation\Api\Dto\Output\WebhookEventTypeOutput;

use function array_map;

/**
 * Provider WebhookEventTypeProvider.
 *
 * Static reference catalog (`GET /webhooks/event-types`) — the curated
 * allowlist a subscription may register for. Kept thin: it reads directly
 * from the installed contributor catalog, per ARCHITECTURE.md's
 * reference-catalog guideline.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<WebhookEventTypeOutput>
 */
final readonly class WebhookEventTypeProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param WebhookEventCatalogPort $eventCatalog the contributed public catalog
   */
  public function __construct(private WebhookEventCatalogPort $eventCatalog)
  {
  }
  // #endregion

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
    return array_map(self::toOutput(...), $this->eventCatalog->descriptors());
  }

  /**
   * Method toOutput.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param WebhookEventDescriptor $descriptor the contributor metadata
   *
   * @return WebhookEventTypeOutput the mapped output
   */
  private static function toOutput(WebhookEventDescriptor $descriptor): WebhookEventTypeOutput
  {
    $output = new WebhookEventTypeOutput();
    $output->value = $descriptor->value;
    $output->label = $descriptor->label;

    return $output;
  }
  // #endregion
}
