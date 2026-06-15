<?php

declare(strict_types=1);

namespace Intervention\Domain\Catalog;

use Intervention\Domain\ValueObject\ReferencePack;

/**
 * Catalog ReferencePackCatalog.
 *
 * Single source of truth for the regulatory reference packs available to
 * interventions. Add packs here to support new countries/regimes.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ReferencePackCatalog
{
  /**
   * Method all.
   *
   * Returns every available reference pack. The first entry is the default.
   *
   * @since 1.0.0
   *
   * @return list<ReferencePack> the available reference packs
   */
  public function all(): array
  {
    return [
      new ReferencePack(
        id: 'fr-erp-ert-v1',
        country: 'FR',
        regime: 'ERP_ERT',
        name: 'France ERP / ERT',
        version: '1.0.0',
        recommendedEquipmentTypes: ['fire_extinguisher', 'fire_alarm', 'emergency_lighting'],
      ),
    ];
  }

  /**
   * Method find.
   *
   * Returns the reference pack matching the given id, or null when unknown.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   *
   * @return ?ReferencePack the matching reference pack
   */
  public function find(string $id): ?ReferencePack
  {
    foreach ($this->all() as $pack) {
      if ($pack->id === $id) {
        return $pack;
      }
    }

    return null;
  }

  /**
   * Method defaultPack.
   *
   * Returns the default reference pack applied when none is selected.
   *
   * @since 1.0.0
   *
   * @return ReferencePack the default reference pack
   */
  public function defaultPack(): ReferencePack
  {
    return $this->all()[0];
  }
}
