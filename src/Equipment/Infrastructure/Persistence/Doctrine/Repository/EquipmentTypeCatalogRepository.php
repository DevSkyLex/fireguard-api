<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Domain\Exception\EquipmentTypeCatalogException;
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;
use Equipment\Domain\ValueObject\EquipmentType;

use function array_values;
use function in_array;
use function ksort;
use function trim;

/**
 * Repository EquipmentTypeCatalogRepository.
 *
 * Merges immutable defaults, persisted overrides and historical assignments.
 * Organization advisory locking serializes custom creation and first default overrides.
 *
 * @category Repository
 */
final readonly class EquipmentTypeCatalogRepository implements EquipmentTypeCatalogPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly wired main manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method list.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   *
   * @return list<EquipmentTypeDefinition> defaults, overrides and historical archived codes
   */
  public function list(string $organizationId): array
  {
    $definitions = [];
    foreach (EquipmentType::cases() as $type) {
      $family = match ($type) {
        EquipmentType::EMERGENCY_LIGHTING, EquipmentType::ACCESS_CONTROL, EquipmentType::CAMERA, EquipmentType::GAS_DETECTOR => 'safety',
        EquipmentType::OTHER => 'other',
        default => 'fire',
      };
      $definitions[$type->value] = new EquipmentTypeDefinition($type->value, $type->label(), $family);
    }

    $connection = $this->entityManager->getConnection();
    /** @var list<string> $legacy */
    $legacy = $connection->fetchFirstColumn('SELECT DISTINCT type FROM equipment WHERE organization_id = :organization', ['organization' => $organizationId]);
    foreach ($legacy as $value) {
      $code = (string) $value;
      if ('' !== trim($code) && !isset($definitions[$code])) {
        $definitions[$code] = new EquipmentTypeDefinition($code, $code, 'other', true);
      }
    }

    /** @var list<array{type_code: string, label: string, family: string, archived: bool|int|string, revision: int|string}> $rows */
    $rows = $connection->fetchAllAssociative('SELECT type_code, label, family, archived, revision FROM equipment_type_catalog WHERE organization_id = :organization', ['organization' => $organizationId]);
    foreach ($rows as $row) {
      $code = (string) $row['type_code'];
      $definitions[$code] = new EquipmentTypeDefinition(
        $code,
        (string) $row['label'],
        (string) $row['family'],
        in_array($row['archived'], [true, 1, '1', 't', 'true'], true),
        (int) $row['revision'],
      );
    }
    ksort($definitions);

    return array_values($definitions);
  }

  /**
   * Method find.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $typeCode stable type code
   *
   * @return EquipmentTypeDefinition|null resolved descriptor
   */
  public function find(string $organizationId, string $typeCode): ?EquipmentTypeDefinition
  {
    $connection = $this->entityManager->getConnection();
    /** @var array{type_code: string, label: string, family: string, archived: bool|int|string, revision: int|string}|false $row */
    $row = $connection->fetchAssociative('SELECT type_code, label, family, archived, revision FROM equipment_type_catalog WHERE organization_id = :organization AND type_code = :code', ['organization' => $organizationId, 'code' => $typeCode]);
    if (false !== $row) {
      return new EquipmentTypeDefinition(
        $row['type_code'],
        $row['label'],
        $row['family'],
        in_array($row['archived'], [true, 1, '1', 't', 'true'], true),
        (int) $row['revision'],
      );
    }

    $type = EquipmentType::tryFrom($typeCode);
    if (null !== $type) {
      $family = match ($type) {
        EquipmentType::EMERGENCY_LIGHTING, EquipmentType::ACCESS_CONTROL, EquipmentType::CAMERA, EquipmentType::GAS_DETECTOR => 'safety',
        EquipmentType::OTHER => 'other',
        default => 'fire',
      };

      return new EquipmentTypeDefinition($type->value, $type->label(), $family);
    }

    if ('' !== trim($typeCode) && false !== $connection->fetchOne('SELECT 1 FROM equipment WHERE organization_id = :organization AND type = :code LIMIT 1', ['organization' => $organizationId, 'code' => $typeCode])) {
      return new EquipmentTypeDefinition($typeCode, $typeCode, 'other', true);
    }

    return null;
  }

  /**
   * Method save.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param EquipmentTypeDefinition $definition descriptor to store
   * @param int|null $expectedRevision null for create
   *
   * @return void
   */
  public function save(string $organizationId, EquipmentTypeDefinition $definition, ?int $expectedRevision): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->transactional(function (Connection $connection) use ($organizationId, $definition, $expectedRevision): void {
      $connection->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'equipment-type-catalog:' . $organizationId]);
      $current = $this->find($organizationId, $definition->value);
      if (null === $expectedRevision && null !== $current) {
        throw new EquipmentTypeCatalogException('equipment_type_exists', 'An equipment type with this code already exists.');
      }
      if (null !== $expectedRevision && (null === $current || $current->revision !== $expectedRevision)) {
        throw new EquipmentTypeCatalogException('equipment_type_revision_conflict', 'The equipment type changed. Reload before editing.');
      }
      $connection->executeStatement(
        'INSERT INTO equipment_type_catalog (organization_id, type_code, label, family, archived, revision) VALUES (:organization, :code, :label, :family, :archived, :revision) ON CONFLICT (organization_id, type_code) DO UPDATE SET label = EXCLUDED.label, family = EXCLUDED.family, archived = EXCLUDED.archived, revision = EXCLUDED.revision',
        ['organization' => $organizationId, 'code' => $definition->value, 'label' => $definition->label, 'family' => $definition->family, 'archived' => $definition->archived, 'revision' => $definition->revision],
        ['archived' => \Doctrine\DBAL\ParameterType::BOOLEAN],
      );
    });
  }

  /**
   * Method validateAvailableType.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $type candidate type
   * @param string|null $existingType current persisted type
   *
   * @return void
   */
  public function validateAvailableType(string $organizationId, string $type, ?string $existingType = null): void
  {
    if ($existingType === $type) {
      return;
    }

    $definition = $this->find($organizationId, $type);
    if (null === $definition) {
      throw new EquipmentTypeCatalogException('equipment_type_unknown', 'This equipment type is not in the organization catalog.');
    }

    $definition->assertAvailable($existingType);
  }
  // #endregion
}
