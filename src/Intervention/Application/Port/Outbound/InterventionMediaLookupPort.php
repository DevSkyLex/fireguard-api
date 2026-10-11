<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Attachment\InterventionAttachmentView;

/** Reads attachment metadata without exposing persistence.
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionMediaLookupPort
{
  public function attachment(string $id, bool $lock = false): ?InterventionAttachmentView;

  public function organizationId(string $interventionId): ?string;
}
