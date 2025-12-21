<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Persistence\Doctrine\Mapper;

use ReflectionClass;
use Symfony\Component\Uid\Uuid;
use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;
use Tenant\Domain\ValueObject\TenantName;
use Tenant\Domain\ValueObject\TenantSettings;
use Tenant\Infrastructure\Persistence\Doctrine\Record\TenantRecord;

/**
 * Mapper TenantMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * Maps a TenantRecord to a Tenant domain model.
   *
   * @since 1.0.0
   *
   * @param TenantRecord $record the record to map
   *
   * @return Tenant the domain model
   */
  public static function toDomain(TenantRecord $record): Tenant
  {
    $reflection = new ReflectionClass(objectOrClass: Tenant::class);
    $tenant = $reflection->newInstanceWithoutConstructor();

    $idProperty = $reflection->getProperty(name: 'id');
    $idProperty->setValue($tenant, TenantId::fromString(value: $record->id->toRfc4122()));

    $nameProperty = $reflection->getProperty(name: 'name');
    $nameProperty->setValue($tenant, new TenantName(value: $record->name));

    $settingsProperty = $reflection->getProperty(name: 'settings');
    $settingsProperty->setValue($tenant, TenantSettings::fromArray(data: $record->settings));

    $isActiveProperty = $reflection->getProperty(name: 'isActive');
    $isActiveProperty->setValue($tenant, $record->isActive);

    $createdAtProperty = $reflection->getProperty(name: 'createdAt');
    $createdAtProperty->setValue($tenant, $record->createdAt);

    return $tenant;
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * Maps a Tenant domain model to a TenantRecord.
   *
   * @since 1.0.0
   *
   * @param Tenant $tenant the domain model to map
   *
   * @return TenantRecord the record
   */
  public static function toRecord(Tenant $tenant): TenantRecord
  {
    $record = new TenantRecord();

    $record->id = Uuid::fromString(uuid: (string) $tenant->id());
    $record->name = (string) $tenant->name();
    $record->settings = $tenant->settings()->toArray();
    $record->isActive = $tenant->isActive();
    $record->createdAt = $tenant->createdAt();

    return $record;
  }
  // #endregion
}
