<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia;

use Shared\Application\Message\QueryMessage;

/** Resolves the scoped media target before HTTP payload validation.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionMediaQuery implements QueryMessage
{
  public function __construct(
    public string $userId,
    public ?string $attachmentId = null,
    public ?string $inspectionId = null,
    public ?string $nonConformityId = null,
    public bool $write = false,
    public bool $deletion = false,
  ) {
  }
}
