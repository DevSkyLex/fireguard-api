<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Reporting\ListMaintenanceEconomicDossiers;

use Intervention\Application\Contract\Publication\InterventionEconomicContextPage;
use Shared\Application\Message\ResultMessage;

/**
 * Class ListMaintenanceEconomicDossiersResult
 *
 * Carries minimal scoped identities and an exact directory count.
 *
 * @category Result
 */
final readonly class ListMaintenanceEconomicDossiersResult implements ResultMessage
{
  /**
   * @param array<string,list<array{id:string,name:?string,assetReference:?string,site:?array{id:string,name:string},customer:?array{id:string,name:string}}>> $equipment minimal financial identities indexed by intervention
   */
  public function __construct(public InterventionEconomicContextPage $page, public array $equipment = [])
  {
  }
}
