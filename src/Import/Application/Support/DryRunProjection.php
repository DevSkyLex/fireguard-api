<?php

declare(strict_types=1);

namespace Import\Application\Support;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;

use function dechex;
use function hexdec;
use function pack;
use function sha1;
use function sprintf;
use function str_replace;
use function substr;

/**
 * Support DryRunProjection.
 *
 * Running, in-memory state `ProcessImportJobHandler` threads through a
 * dry-run batch as it walks the CSV file row by row: how many rows have
 * already been reported `would_create` for each resource kind (the
 * quota-projection offset a provisioning port needs to answer "would this
 * row, plus everything already counted in this same batch, exceed the
 * cap"), and — facility imports only — the identifiers, types, ancestry and codes of rows reported
 * `would_create` so far, so a child row's `parentCode` can resolve against a
 * parent that would itself be created earlier in the same file rather than
 * only against what is already in the database. A real (write) run never
 * builds one: rows are provisioned as they are read, so the database itself
 * carries this state.
 *
 * @category Support
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DryRunProjection
{
  // #region Properties
  /**
   * Property equipmentCount.
   *
   * @since 1.0.0
   */
  private int $equipmentCount = 0;

  /**
   * Property facilityCount.
   *
   * @since 1.0.0
   */
  private int $facilityCount = 0;

  /**
   * Property facilityPendingCodeIds.
   *
   * @since 1.0.0
   *
   * @var array<string, string>
   */
  private array $facilityPendingCodeIds = [];

  /**
   * Property facilityHierarchy.
   *
   * @var list<FacilityHierarchyNode>
   */
  private array $facilityHierarchy = [];
  // #endregion

  // #region Methods
  /**
   * Method equipmentCount.
   *
   * @since 1.0.0
   */
  public function equipmentCount(): int
  {
    return $this->equipmentCount;
  }

  /**
   * Method recordEquipmentWouldCreate.
   *
   * @since 1.0.0
   */
  public function recordEquipmentWouldCreate(): void
  {
    ++$this->equipmentCount;
  }

  /**
   * Method facilityCount.
   *
   * @since 1.0.0
   */
  public function facilityCount(): int
  {
    return $this->facilityCount;
  }

  /**
   * Method facilityPendingCodeIds.
   *
   * @since 1.0.0
   *
   * @return array<string, string> codes mapped to identifiers of earlier successful simulated rows
   */
  public function facilityPendingCodeIds(): array
  {
    return $this->facilityPendingCodeIds;
  }

  /**
   * Method recordFacilityWouldCreate.
   *
   * @since 1.0.0
   *
   * @param ?string $code the row's own code, when it has one
   * @param ?FacilityHierarchyNode $node the retained node, absent when an old parent is now unavailable
   */
  public function recordFacilityWouldCreate(?string $code, ?FacilityHierarchyNode $node): void
  {
    ++$this->facilityCount;

    if (null === $node) {
      return;
    }
    $this->facilityHierarchy[] = $node;
    if (null !== $code && !isset($this->facilityPendingCodeIds[$code])) {
      $this->facilityPendingCodeIds[$code] = $node->id;
    }
  }

  /**
   * Method facilityHierarchy.
   *
   * @return list<FacilityHierarchyNode> nodes reconstructed from successful simulated rows
   */
  public function facilityHierarchy(): array
  {
    return $this->facilityHierarchy;
  }

  /**
   * Method simulatedFacilityId.
   *
   * Derives an RFC 9562 UUID v5 from the job namespace and original CSV row.
   * Uses the retained job identifier as UUID namespace so redelivery reconstructs
   * exactly the same identities without storing or reserving facilities.
   *
   * @param string $jobId the immutable import job identifier
   * @param int $rowNumber the original one-based CSV data row
   *
   * @return string the stable UUID of this simulated facility
   */
  public static function simulatedFacilityId(string $jobId, int $rowNumber): string
  {
    $digest = sha1(pack('H*', str_replace('-', '', $jobId)) . 'import-facility:' . $rowNumber);
    $digest[12] = '5';
    $digest[16] = dechex(((int) hexdec($digest[16]) & 0x03) | 0x08);

    return sprintf('%s-%s-%s-%s-%s', substr($digest, 0, 8), substr($digest, 8, 4), substr($digest, 12, 4), substr($digest, 16, 4), substr($digest, 20, 12));
  }
  // #endregion
}
