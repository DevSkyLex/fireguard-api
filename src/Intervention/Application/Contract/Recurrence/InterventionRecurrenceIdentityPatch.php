<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

/**
 * Identity overrides and their merge-patch presence flags.
 */
final readonly class InterventionRecurrenceIdentityPatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries recurrence identity overrides with field-presence information.
   *
   * @access public
   *
   * @param ?string $name replacement recurrence name, when supplied
   * @param ?string $siteId replacement optional site identifier
   * @param ?string $responsibleId replacement optional responsible-member identifier
   * @param bool $hasName whether name was included in the patch
   * @param bool $hasSiteId whether site identifier was included
   * @param bool $hasResponsibleId whether responsible identifier was included
   *
   * @return void
   */
  public function __construct(
    public ?string $name,
    public ?string $siteId,
    public ?string $responsibleId,
    public bool $hasName,
    public bool $hasSiteId,
    public bool $hasResponsibleId,
  ) {
  }
  // #endregion
}
