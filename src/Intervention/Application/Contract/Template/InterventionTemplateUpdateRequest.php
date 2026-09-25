<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * A validated template merge-patch without loss of field presence.
 */
final readonly class InterventionTemplateUpdateRequest
{
  public function __construct(
    public string $id,
    public InterventionTemplateIdentityPatch $identity,
    public InterventionTemplatePlanningPatch $planning,
    public InterventionTemplateDefaultsPatch $defaults,
    public InterventionTemplateCollectionsPatch $collections,
  ) {
  }
}
