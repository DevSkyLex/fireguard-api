<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Park;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities\OrganizationNonConformityResult;
use Inspection\Application\UseCase\Query\Park\GetParkAnomaliesSummary\{GetParkAnomaliesSummaryQuery, GetParkAnomaliesSummaryResult};
use Inspection\Application\UseCase\Query\Park\ListParkAnomalies\ListParkAnomaliesQuery;
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Dto\Output\Park\ParkAnomaliesSummaryOutput;
use Inspection\Presentation\Api\Operation\ParkAnomaliesOperations;
use Inspection\Presentation\Api\Service\ParkAnomaliesAccess;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\RequestStack;

use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Projects the same scoped unresolved anomaly queue as either rows or counters.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<NonConformityOutput|ParkAnomaliesSummaryOutput>
 */
final readonly class ParkAnomaliesProvider implements ProviderInterface
{
  /**
   * @since 1.0.0
   */
  public function __construct(private QueryBusPort $queries, private ParkAnomaliesAccess $access, private RequestStack $requests)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables owner identifiers
   * @param array<string, mixed> $context provider context
   *
   * @return ParkAnomaliesSummaryOutput|TraversablePaginator<NonConformityOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ParkAnomaliesSummaryOutput|TraversablePaginator
  {
    $organizationId = $this->access->organization($uriVariables);
    $parameters = $this->requests->getCurrentRequest()?->query;
    $family = self::optional($parameters?->get('family'));
    $customerId = self::optional($parameters?->get('customerId'));
    $facilityId = self::optional($parameters?->get('facilityId'));
    $includeDescendants = $parameters?->getBoolean('includeDescendants', true) ?? true;
    if (ParkAnomaliesOperations::SUMMARY === $operation->getName()) {
      /** @var GetParkAnomaliesSummaryResult $result */
      $result = $this->queries->ask(new GetParkAnomaliesSummaryQuery($organizationId, $family, $customerId, $facilityId, $includeDescendants));
      $output = new ParkAnomaliesSummaryOutput();
      $output->openAnomalies = $result->openAnomalies;
      $output->bySeverity = $result->bySeverity;

      return $output;
    }
    $pageValue = $parameters?->get('page', '1');
    $sizeValue = $parameters?->get('itemsPerPage', '30');
    $page = max(1, is_numeric($pageValue) ? (int) $pageValue : 1);
    $size = max(1, min(100, is_numeric($sizeValue) ? (int) $sizeValue : 30));
    /** @var PaginatedResult<OrganizationNonConformityResult> $result */
    $result = $this->queries->ask(new ListParkAnomaliesQuery($organizationId, $family, $customerId, $facilityId, new Pagination(($page - 1) * $size, $size), includeDescendants: $includeDescendants));
    $outputs = [];
    foreach ($result->items as $row) {
      $outputs[] = self::map($row);
    }

    return new TraversablePaginator(new ArrayIterator($outputs), (float) $page, (float) $size, (float) $result->total);
  }

  /**
   * @since 1.0.0
   */
  private static function optional(mixed $value): ?string
  {
    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * @since 1.0.0
   */
  private static function map(OrganizationNonConformityResult $row): NonConformityOutput
  {
    $output = new NonConformityOutput();
    $output->id = $row->nonConformityId;
    $output->inspectionId = $row->inspectionId;
    $output->description = $row->description;
    $output->severity = $row->severity;
    $output->status = $row->status;
    $output->dueAt = $row->dueAt;
    $output->resolvedAt = $row->resolvedAt;
    $output->notes = $row->notes;
    $output->equipmentId = $row->equipmentId;
    $output->equipmentSerialNumber = $row->equipmentSerialNumber;
    $output->createdAt = $row->createdAt->format('c');
    $output->updatedAt = $row->updatedAt->format('c');

    return $output;
  }
}
