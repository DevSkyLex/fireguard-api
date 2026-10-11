<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia;

use Inspection\Application\Contract\Attachment\InspectionAttachmentView;
use Shared\Application\Message\ResultMessage;

/** Authorized media scope and optional stored attachment.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionMediaResult implements ResultMessage
{
  public function __construct(public string $organizationId, public string $inspectionId, public ?InspectionAttachmentView $attachment = null)
  {
  }
}
