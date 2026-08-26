<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, InspectionResponseId};

/**
 * Port InspectionResponseRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InspectionResponseRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an inspection response, inserting or updating in place.
   *
   * @since 1.0.0
   *
   * @param InspectionResponse $response the response aggregate
   */
  public function save(InspectionResponse $response): void;

  /**
   * Method findById.
   *
   * Finds a response by identifier.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseId $id the response identifier
   *
   * @return ?InspectionResponse the response when found
   */
  public function findById(InspectionResponseId $id): ?InspectionResponse;

  /**
   * Method existsByClientId.
   *
   * Tells whether an offline client identifier is already stored — the
   * replay guard of the two creation operations.
   *
   * @since 1.0.0
   *
   * @param string $clientId the offline client identifier
   *
   * @return bool true when a response already carries that client identifier
   */
  public function existsByClientId(string $clientId): bool;

  /**
   * Method delete.
   *
   * Deletes a response. A missing row is not an error: the caller has
   * already established the response exists, and a concurrent delete must
   * not turn into a 500.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseId $id the response identifier
   */
  public function delete(InspectionResponseId $id): void;

  /**
   * Method findByFilters.
   *
   * Lists an organization's responses, oldest first, optionally narrowed to
   * one intervention and/or one inspection.
   *
   * `recordStatus` is always applied: the collection endpoint never mixes
   * drafts with published rows, because they answer different questions —
   * what a field client is preparing versus what the compliance record says.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the owning organization
   * @param ?string $interventionId narrow to one intervention, or null
   * @param ?string $inspectionId narrow to one inspection, or null
   * @param string $recordStatus the representation lifecycle status
   * @param int $limit the page size
   * @param int $offset the page offset
   *
   * @return list<InspectionResponse> the page of responses
   */
  public function findByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $inspectionId,
    string $recordStatus,
    int $limit,
    int $offset,
  ): array;

  /**
   * Method countByFilters.
   *
   * Counts the rows `findByFilters()` would page over, with the same filters
   * and without hydrating them.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the owning organization
   * @param ?string $interventionId narrow to one intervention, or null
   * @param ?string $inspectionId narrow to one inspection, or null
   * @param string $recordStatus the representation lifecycle status
   *
   * @return int the total row count
   */
  public function countByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $inspectionId,
    string $recordStatus,
  ): int;
  // #endregion
}
