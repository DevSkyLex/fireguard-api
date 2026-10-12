<?php

declare(strict_types=1);

namespace Tests\Support\Catalogs;

use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Port\Outbound\ApprovalActionExecutorPort;
use Approval\Application\Service\ApprovalActionCatalogRegistry;
use Equipment\Infrastructure\Adapter\Approval\EquipmentDecommissionDescriptorAdapter;
use Equipment\Infrastructure\Adapter\Webhook\EquipmentWebhookEventSourceAdapter;
use Facility\Infrastructure\Adapter\Webhook\FacilityWebhookEventSourceAdapter;
use Inspection\Infrastructure\Adapter\Approval\NonConformityWaiverDescriptorAdapter;
use Inspection\Infrastructure\Adapter\Webhook\InspectionWebhookEventSourceAdapter;
use Intervention\Infrastructure\Adapter\Webhook\InterventionWebhookEventSourceAdapter;
use Maintenance\Infrastructure\Adapter\Webhook\MaintenanceWebhookEventSourceAdapter;
use Webhook\Application\Contract\Event\WebhookEventProjection;
use Webhook\Application\Port\Inbound\WebhookEventPublisherPort;
use Webhook\Application\Service\WebhookEventCatalogRegistry;

/**
 * Class BuiltInCapabilityCatalogs
 *
 * Builds installed metadata for unit tests without booting the application container.
 *
 * @category Test Support
 */
final class BuiltInCapabilityCatalogs
{
  // #region Methods
  /**
   * Method approval
   *
   * @access public
   *
   * @return ApprovalActionCatalogRegistry the existing action contributions
   */
  public static function approval(): ApprovalActionCatalogRegistry
  {
    $providers = [new NonConformityWaiverDescriptorAdapter(), new EquipmentDecommissionDescriptorAdapter()];
    $executors = [];
    foreach ($providers as $provider) {
      $executors[] = new readonly class ($provider->descriptor()->value) implements ApprovalActionExecutorPort {
        public function __construct(private string $type)
        {
        }

        public function actionType(): string
        {
          return $this->type;
        }

        public function execute(DeferredActionContext $context): void
        {
        }
      };
    }

    return new ApprovalActionCatalogRegistry($providers, $executors);
  }

  /**
   * Method webhook
   *
   * @access public
   *
   * @return WebhookEventCatalogRegistry the existing curated source contributions
   */
  public static function webhook(): WebhookEventCatalogRegistry
  {
    $publisher = new readonly class () implements WebhookEventPublisherPort {
      public function publish(WebhookEventProjection $projection): void
      {
      }
    };

    return new WebhookEventCatalogRegistry([
      new EquipmentWebhookEventSourceAdapter($publisher),
      new InspectionWebhookEventSourceAdapter($publisher),
      new InterventionWebhookEventSourceAdapter($publisher),
      new MaintenanceWebhookEventSourceAdapter($publisher),
      new FacilityWebhookEventSourceAdapter($publisher),
    ]);
  }
  // #endregion
}
