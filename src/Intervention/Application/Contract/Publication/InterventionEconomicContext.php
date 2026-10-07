<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

use DateTimeImmutable;

/**
 * Class InterventionEconomicContext
 *
 * Supplies non-financial allocation identities, explicitly distinguishing live work from immutable published history.
 *
 * @category Contract
 */
final readonly class InterventionEconomicContext
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Published work never substitutes current identities when its original dossier is incomplete or missing.
   *
   * @access public
   *
   * @param string $id stable intervention identifier
   * @param string $organizationId owning organization
   * @param int $number stable organization sequence
   * @param string $name captured title for published dossiers
   * @param string $type operation category
   * @param string $status workflow status
   * @param int $revision operational revision
   * @param DateTimeImmutable $createdAt original creation time
   * @param ?DateTimeImmutable $plannedStartAt scheduled start
   * @param ?DateTimeImmutable $dueAt scheduled deadline
   * @param ?DateTimeImmutable $publishedAt original completed publication time
   * @param ?string $publicationId completed publication identifier
   * @param 'available'|'snapshot_missing'|'live' $snapshotState source provenance
   * @param ?int $snapshotVersion retained dossier version
   * @param bool $identityComplete whether all equipment targets have captured identity
   * @param ?array{id:string,name:string} $site root site identity
   * @param ?array{id:string,name:string} $customer optional internal client identity
   * @param list<InterventionPublishedWorkFact> $workItems task allocation facts
   *
   * @return void
   */
  public function __construct(public string $id, public string $organizationId, public int $number, public string $name, public string $type, public string $status, public int $revision, public DateTimeImmutable $createdAt, public ?DateTimeImmutable $plannedStartAt, public ?DateTimeImmutable $dueAt, public ?DateTimeImmutable $publishedAt, public ?string $publicationId, public string $snapshotState, public ?int $snapshotVersion, public bool $identityComplete, public ?array $site, public ?array $customer, public array $workItems)
  {
  }
  // #endregion
}
