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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalInspectionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries only submitted inspection fields; has-field flags distinguish omission from explicitly clearing a nullable value.
   *
   * @access public
   *
   * @param string $inspectionId inspection to update
   * @param int $expectedRevision revision read by the caller before submitting changes
   * @param bool $hasResult whether the result field was included in the request
   * @param ?string $result new result, or null when explicitly cleared
   * @param bool $hasStatus whether the status field was included in the request
   * @param ?string $status new status, or null when explicitly cleared
   * @param bool $hasNotes whether the notes field was included in the request
   * @param ?string $notes new notes, or null when explicitly cleared
   * @param bool $hasSignature whether the signature field was included in the request
   * @param ?string $signature new signature data, or null when explicitly cleared
   *
   * @return void
   */
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
