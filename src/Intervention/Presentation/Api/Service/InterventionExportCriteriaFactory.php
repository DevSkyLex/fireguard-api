<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Service;

use Intervention\Domain\ValueObject\{InterventionPriority, InterventionStatus, InterventionType};
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_string;

/**
 * Service InterventionExportCriteriaFactory.
 *
 * Builds the `array<string, mixed>` filter shape {@see \Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort::countInterventions()}/
 * {@see \Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort::listInterventionExportCandidates()}
 * expect, from the export controller's raw `Request` query string — the
 * documented export filter subset (`name`, `type`, `status`, `priority`,
 * `dueAtAfter`, `dueAtBefore`, `site`, `responsible`, `due=overdue`) of the
 * larger set {@see \Intervention\Presentation\Api\Provider\InterventionProvider}
 * parses inline for the list endpoint, so an export always matches what the
 * caller is currently filtering on. Mirrors
 * `Audit\...\AuditEventExportCriteriaFactory`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionExportCriteriaFactory
{
  // #region Methods
  /**
   * Method fromRequest.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return array<string, mixed> the parsed filters
   */
  public function fromRequest(Request $request): array
  {
    $query = $request->query;
    $filters = [];

    $name = $query->get('name');
    if (is_string($name) && '' !== $name) {
      $filters['name'] = $name;
    }

    $enumGuards = [
      'type' => [InterventionType::tryFrom(...), 'The type filter must be one of: site_setup, inventory, inspection_campaign.'],
      'status' => [InterventionStatus::tryFrom(...), 'The status filter must be a known intervention status.'],
      'priority' => [InterventionPriority::tryFrom(...), 'The priority filter must be one of: low, normal, high, urgent.'],
    ];
    foreach ($enumGuards as $filter => [$tryFrom, $message]) {
      $values = $this->multiValue($query->all()[$filter] ?? null);
      if ([] === $values) {
        continue;
      }
      foreach ($values as $value) {
        if (null === $tryFrom($value)) {
          throw new BadRequestHttpException($message);
        }
      }
      $filters[$filter] = $values;
    }

    foreach (['dueAtAfter', 'dueAtBefore'] as $filter) {
      $value = $query->get($filter);
      if (is_string($value) && '' !== $value) {
        $filters[$filter] = $value;
      }
    }

    $site = $query->get('site');
    if (is_string($site) && '' !== $site) {
      $filters['siteId'] = ResourceIriParser::id($site, 'facilities');
    }

    $responsible = $query->get('responsible');
    if (is_string($responsible) && '' !== $responsible) {
      $filters['responsibleId'] = ResourceIriParser::memberId($responsible);
    }

    $due = $query->get('due');
    if (is_string($due) && '' !== $due) {
      if ('overdue' !== $due) {
        throw new BadRequestHttpException('The due filter must be: overdue.');
      }
      $filters['due'] = $due;
    }

    return $filters;
  }

  /**
   * Method appliedFilterKeys.
   *
   * Returns the names of the filters actually applied — used only to
   * populate the export's own audit-trail metadata, which must never carry
   * raw filter values.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the resolved filters
   *
   * @return list<string> the applied filter field names
   */
  public function appliedFilterKeys(array $filters): array
  {
    return array_keys($filters);
  }

  /**
   * Method multiValue.
   *
   * Normalizes a query value that accepts repetition (`status[]=a&status[]=b`)
   * while still taking the single scalar form (`status=a`), mirroring
   * `InterventionProvider::multiValue()`.
   *
   * @since 1.0.0
   *
   * @param mixed $raw the raw query value
   *
   * @return list<string> the normalized values
   */
  private function multiValue(mixed $raw): array
  {
    if (is_string($raw) && '' !== $raw) {
      return [$raw];
    }
    if (!is_array($raw)) {
      return [];
    }

    return array_values(array_filter(
      array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $raw),
      static fn (string $value): bool => '' !== $value,
    ));
  }
  // #endregion
}
