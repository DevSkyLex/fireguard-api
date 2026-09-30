<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Name, description and type overrides with independent presence flags.
 */
final readonly class InterventionTemplateIdentityPatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries template identity overrides with independent presence flags.
   *
   * @access public
   *
   * @param ?string $name replacement template name, when supplied
   * @param ?string $description replacement description, when supplied
   * @param ?string $type replacement intervention type, when supplied
   * @param bool $hasName whether name was included in the patch
   * @param bool $hasDescription whether description was included in the patch
   * @param bool $hasType whether type was included in the patch
   *
   * @return void
   */
  public function __construct(
    public ?string $name,
    public ?string $description,
    public ?string $type,
    public bool $hasName,
    public bool $hasDescription,
    public bool $hasType,
  ) {
  }
  // #endregion
}
