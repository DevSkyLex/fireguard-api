<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Import;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;

use function dechex;
use function hexdec;
use function pack;
use function sha1;
use function sprintf;
use function str_replace;
use function substr;

/**
 * Support FacilityImportProjection.
 *
 * Facility-owned running simulation state. Successful rows contribute quota
 * offsets, stable identifiers and typed hierarchy nodes in original file order.
 * Resumption reconstructs this projection without provisioning resources.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityImportProjection
{
  // #region Properties
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
