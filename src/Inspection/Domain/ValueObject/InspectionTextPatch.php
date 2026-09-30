<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/** Optional note and signature edits with independent presence flags. */
final readonly class InspectionTextPatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries note and signature values with independent presence flags.
   *
   * @access public
   *
   * @param ?string $notes replacement notes, when supplied
   * @param bool $hasNotes whether the notes field was included
   * @param ?string $signature replacement signature, when supplied
   * @param bool $hasSignature whether the signature field was included
   *
   * @return void
   */
  public function __construct(
    public ?string $notes = null,
    public bool $hasNotes = false,
    public ?string $signature = null,
    public bool $hasSignature = false,
  ) {
  }
  // #endregion
}
