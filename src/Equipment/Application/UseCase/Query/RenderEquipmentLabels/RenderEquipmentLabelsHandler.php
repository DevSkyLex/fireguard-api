<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\RenderEquipmentLabels;

use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Application\Port\Outbound\EquipmentLabelSheetPdfRendererPort;
use Equipment\Application\UseCase\Query\ExportEquipmentLabels\{ExportEquipmentLabelsQuery, ExportEquipmentLabelsResult};
use Equipment\Domain\Event\Export\EquipmentLabelsExportedEvent;
use Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_map;
use function sprintf;

/** Renders labels and emits the owner export event after successful rendering. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class RenderEquipmentLabelsHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(
    private QueryBusPort $queries,
    private OrganizationDocumentBrandingPort $branding,
    private EquipmentLabelSheetPdfRendererPort $renderer,
    private EventDispatcherPort $events,
  ) {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(RenderEquipmentLabelsQuery $query): RenderEquipmentLabelsResult
  {
    /** @var ExportEquipmentLabelsResult $result */
    $result = $this->queries->ask(new ExportEquipmentLabelsQuery($query->userId, $query->organizationId, $query->equipmentIds, $query->facilityId));
    $contents = $this->renderer->render([
      'lang' => $this->branding->getDocumentBranding($query->organizationId)->language(),
      'labels' => array_map(static fn (EquipmentExportRow $row): array => [
        'qrValue' => sprintf('/api/equipment/%s', $row->id), 'type' => $row->type, 'subType' => $row->subType,
        'serialNumber' => $row->serialNumber, 'facilityName' => $row->facilityName, 'locationLabel' => $row->locationLabel,
      ], $result->rows),
    ]);
    $this->events->dispatch(new EquipmentLabelsExportedEvent($query->organizationId, $query->userId, $result->selection, $result->total));

    return new RenderEquipmentLabelsResult($contents);
  }
}
