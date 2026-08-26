<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\InspectionResponseId;

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
  // #endregion
}
