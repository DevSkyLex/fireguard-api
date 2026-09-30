<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Default site and responsible member overrides.
 */
final readonly class InterventionTemplateDefaultsPatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries template assignment overrides with field-presence flags.
   *
   * @access public
   *
   * @param ?string $siteId replacement optional site identifier
   * @param ?string $responsibleId replacement optional responsible-member identifier
   * @param bool $hasSiteId whether the site field was included
   * @param bool $hasResponsibleId whether the responsible-member field was included
   *
   * @return void
   */
  public function __construct(
    public ?string $siteId,
    public ?string $responsibleId,
    public bool $hasSiteId,
    public bool $hasResponsibleId,
  ) {
  }
  // #endregion
}
