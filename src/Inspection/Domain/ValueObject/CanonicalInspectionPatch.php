<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/**
 * ValueObject CanonicalInspectionPatch.
 *
 * One JSON Merge Patch over the canonical inspection surface, expressed as
 * four present/value pairs.
 *
 * The `has*` flags are the whole point: merge-patch distinguishes "the key was
 * absent" from "the key was sent as null", and the two mean opposite things
 * here. An absent `notes` leaves the stored note alone; `"notes": null` erases
 * it. An absent `status` is a no-op; `"status": null` is a rejection.
 *
 * The flags come from the raw request body — `Shared\Presentation\Api\Http\MergePatchFields`
 * reads which keys were present — because a deserialized DTO cannot carry that
 * distinction: both cases arrive as a null property.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalInspectionPatch
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $hasResult whether the `result` key was present
   * @param ?string $result the requested result
   * @param bool $hasStatus whether the `status` key was present
   * @param ?string $status the requested status
   * @param bool $hasNotes whether the `notes` key was present
   * @param ?string $notes the requested notes, null erasing them
   * @param bool $hasSignature whether the `signature` key was present
   * @param ?string $signature the requested signature, null erasing it
   */
  public function __construct(
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
