<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Intervention;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Outbound\InterventionEquipmentSummaryPort;

/** Reads scoped target summaries in a single batch. @category Adapter @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class InterventionEquipmentSummaryAdapter implements InterventionEquipmentSummaryPort
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * {@inheritdoc} @param list<string> $equipmentIds @return array<string, array{type: string, serialNumber: ?string}> @since 1.0.0
   */
  public function summaries(string $organizationId, array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }
    /** @var list<array{id: string, type: string, serial_number: ?string}> $rows */
    $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, type, serial_number FROM equipment WHERE organization_id = :organization AND id IN (:ids)', ['organization' => $organizationId, 'ids' => $equipmentIds], ['ids' => ArrayParameterType::STRING]);
    $result = [];
    foreach ($rows as $row) {
      $result[$row['id']] = ['type' => $row['type'], 'serialNumber' => $row['serial_number']];
    }

    return $result;
  }
}
