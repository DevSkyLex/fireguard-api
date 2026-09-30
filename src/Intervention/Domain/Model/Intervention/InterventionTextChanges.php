<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

/** Text edits with explicit presence flags for merge-patch null semantics. */
final readonly class InterventionTextChanges
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries intervention text changes with merge-patch presence flags.
   *
   * @access public
   *
   * @param ?string $name replacement intervention name, when supplied
   * @param ?string $description replacement description, when supplied
   * @param ?string $reviewNote replacement review note, when supplied
   * @param bool $hasName whether name was included in the patch
   * @param bool $hasDescription whether description was included
   * @param bool $hasReviewNote whether review note was included
   *
   * @return void
   */
  public function __construct(
    public ?string $name = null,
    public ?string $description = null,
    public ?string $reviewNote = null,
    public bool $hasName = false,
    public bool $hasDescription = false,
    public bool $hasReviewNote = false,
  ) {
  }
  // #endregion
}
