<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

use DateTimeImmutable;

/**
 * Class InterventionPublishedWorkFact
 *
 * Maps a task to its owned asset and immutable execution facts; unallocated tasks remain explicit.
 *
 * @category Contract
 */
final readonly class InterventionPublishedWorkFact
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Validation comes from an explicit result or an atomically published closed inspection; missing historical result details remain null.
   *
   * @access public
   *
   * @param string $id stable work-item identifier
   * @param string $action operation code
   * @param string $status execution status
   * @param ?string $target captured canonical or historical target
   * @param ?string $resultResource captured execution resource
   * @param ?string $equipmentId identified target, without live historic lookup
   * @param ?array{id:string,name:string} $site captured root site
   * @param ?array{id:string,name:string} $customer captured internal client
   * @param ?InterventionEquipmentSnapshot $equipmentIdentity actual asset identity when available
   * @param ?array<string,mixed> $executionResult captured outcome and performed work
   * @param bool $validated explicit validation state
   * @param int $spentMinutes known uncancelled whole minutes
   * @param int $evidenceCount known attached proofs
   * @param ?DateTimeImmutable $createdAt captured creation time
   * @param ?DateTimeImmutable $updatedAt captured modification time
   * @param 'execution_result'|'published_inspection'|null $validationSource original validation evidence, without inventing missing result details
   *
   * @return void
   */
  public function __construct(public string $id, public string $action, public string $status, public ?string $target, public ?string $resultResource, public ?string $equipmentId, public ?array $site, public ?array $customer, public ?InterventionEquipmentSnapshot $equipmentIdentity, public ?array $executionResult, public bool $validated, public int $spentMinutes, public int $evidenceCount, public ?DateTimeImmutable $createdAt, public ?DateTimeImmutable $updatedAt, public ?string $validationSource = null)
  {
  }
  // #endregion
}
