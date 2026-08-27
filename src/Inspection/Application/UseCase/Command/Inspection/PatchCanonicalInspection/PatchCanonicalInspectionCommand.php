<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PatchCanonicalInspectionCommand.
 *
 * The `has*` flags carry the merge-patch distinction the deserialized DTO
 * cannot: an absent key and an explicit `null` both arrive as a null
 * property, and they mean opposite things. `MergePatchFields` reads the raw
 * body in the processor and fills them.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalInspectionCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $inspectionId,
    public int $expectedRevision,
    public bool $hasResult = false,
    public ?string $result = null,
    public bool $hasStatus = false,
    public ?string $status = null,
    public bool $hasNotes = false,
    public ?string $notes = null,
    public bool $hasSignature = false,
    public ?string $signature = null,
  ) {
  }
  // #endregion
}
