<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * A validated template merge-patch without loss of field presence.
 */
final readonly class InterventionTemplateUpdateRequest
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Groups validated template patch sections while preserving field presence.
   *
   * @access public
   *
   * @param string $id template identifier to update
   * @param InterventionTemplateIdentityPatch $identity identity fields supplied by the patch
   * @param InterventionTemplatePlanningPatch $planning planning fields supplied by the patch
   * @param InterventionTemplateDefaultsPatch $defaults default assignments supplied by the patch
   * @param InterventionTemplateCollectionsPatch $collections template collections supplied by the patch
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public InterventionTemplateIdentityPatch $identity,
    public InterventionTemplatePlanningPatch $planning,
    public InterventionTemplateDefaultsPatch $defaults,
    public InterventionTemplateCollectionsPatch $collections,
  ) {
  }
  // #endregion
}
