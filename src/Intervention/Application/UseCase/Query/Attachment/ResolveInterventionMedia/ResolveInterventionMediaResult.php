<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia;

use Intervention\Application\Contract\Attachment\InterventionAttachmentView;
use Shared\Application\Message\ResultMessage;

/** Authorized media scope and optional stored attachment.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInterventionMediaResult implements ResultMessage
{
  public function __construct(public string $organizationId, public string $interventionId, public ?InterventionAttachmentView $attachment = null)
  {
  }
}
