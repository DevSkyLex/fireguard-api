<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Application\Contract\Attachment\InspectionAttachmentView;

/** Reads attachment metadata without exposing persistence.
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InspectionMediaLookupPort
{
  public function attachment(string $id, bool $lock = false): ?InspectionAttachmentView;

  public function organizationId(string $inspectionId): ?string;

  /**
   * @return array{inspectionId: string, organizationId: string}|null
   */
  public function nonConformityScope(string $id): ?array;
}
