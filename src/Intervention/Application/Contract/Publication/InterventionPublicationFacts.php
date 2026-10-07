<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Publication;

use DateTimeImmutable;

/**
 * Class InterventionPublicationFacts
 *
 * Exposes a retained operational dossier for versioned exports without resolving historical identities from live records.
 *
 * @category Contract
 */
final readonly class InterventionPublicationFacts
{
  // #region Constructor
  /**
   * Method __construct
   *
   * A missing snapshot is represented explicitly, with no synthetic site, client, task or equipment history.
   *
   * @access public
   *
   * @param string $id stable intervention identifier
   * @param string $organizationId owning organization
   * @param int $number original organization sequence
   * @param string $name captured title when a dossier exists
   * @param string $type captured category
   * @param string $status published workflow state
   * @param int $revision captured operational revision
   * @param DateTimeImmutable $createdAt original creation time
   * @param ?DateTimeImmutable $plannedStartAt captured start
   * @param ?DateTimeImmutable $dueAt captured deadline
   * @param ?DateTimeImmutable $publishedAt original completed publication time
   * @param ?string $publicationId completed publication identifier
   * @param 'available'|'snapshot_missing' $snapshotState original dossier availability
   * @param ?int $snapshotVersion retained version
   * @param bool $identityComplete whether all equipment targets have original captured identity
   * @param ?array{id:string,name:string} $site captured site identity
   * @param ?array{id:string,name:string} $customer captured client identity without contacts
   * @param list<InterventionPublishedWorkFact> $workItems original task facts
   * @param ?array<string,mixed> $dossier retained operational dossier with minimized identity nodes
   *
   * @return void
   */
  public function __construct(public string $id, public string $organizationId, public int $number, public string $name, public string $type, public string $status, public int $revision, public DateTimeImmutable $createdAt, public ?DateTimeImmutable $plannedStartAt, public ?DateTimeImmutable $dueAt, public ?DateTimeImmutable $publishedAt, public ?string $publicationId, public string $snapshotState, public ?int $snapshotVersion, public bool $identityComplete, public ?array $site, public ?array $customer, public array $workItems, public ?array $dossier)
  {
  }
  // #endregion
}
