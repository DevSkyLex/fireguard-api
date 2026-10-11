<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia;

use Shared\Application\Message\QueryMessage;

/** Resolves the scoped media target before HTTP payload validation.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInterventionMediaQuery implements QueryMessage
{
  public function __construct(
    public string $userId,
    public ?string $attachmentId = null,
    public ?string $interventionId = null,
    public bool $replay = false,
    public bool $deletion = false,
  ) {
  }
}
