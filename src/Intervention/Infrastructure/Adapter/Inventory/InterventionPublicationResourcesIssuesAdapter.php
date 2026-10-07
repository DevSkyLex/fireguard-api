<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Inventory;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\Port\Outbound\InterventionPublicationResourcesIssuesPort;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;

/** Class InterventionPublicationResourcesIssuesAdapter. Reports unresolved physical declarations without monetary values. @category Adapter */
final readonly class InterventionPublicationResourcesIssuesAdapter implements InterventionPublicationResourcesIssuesPort
{
  public function __construct(private EntityManagerInterface $entityManager, private InventoryInterventionResourcesPort $inventory)
  {
  }

  public function issues(string $interventionId): array
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$intervention instanceof InterventionRecord || null === $intervention->organization) {
      return [];
    }
    $issues = [];
    foreach ($this->inventory->publicationBlockers($intervention->organization->id, $interventionId) as $reason) {
      $issues[] = new InterventionIssue('blocker', 'intervention', $interventionId, 'resources', $reason);
    }

    return $issues;
  }
}
