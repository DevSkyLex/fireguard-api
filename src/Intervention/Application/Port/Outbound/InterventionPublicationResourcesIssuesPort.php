<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Resource\InterventionIssue;

/** Interface InterventionPublicationResourcesIssuesPort. Non-financial preflight of received resource declarations. @category Port */
interface InterventionPublicationResourcesIssuesPort
{
  /**
   * @return list<InterventionIssue>
   */
  public function issues(string $interventionId): array;
}
