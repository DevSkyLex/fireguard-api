<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Output\Reporting;

use Intervention\Application\Contract\Publication\InterventionEconomicContext;
use Symfony\Component\Serializer\Attribute\Groups;

use function array_values;

/**
 * Class MaintenanceEconomicDossierOutput
 *
 * Supplies minimal private directory labels without operational documents or contacts.
 *
 * @category Output
 */
final class MaintenanceEconomicDossierOutput
{
  #[Groups(['maintenance_economic:read'])]
  public string $id = '';

  #[Groups(['maintenance_economic:read'])]
  public int $number = 0;

  #[Groups(['maintenance_economic:read'])]
  public string $name = '';

  #[Groups(['maintenance_economic:read'])]
  public string $type = '';

  #[Groups(['maintenance_economic:read'])]
  public string $status = '';

  #[Groups(['maintenance_economic:read'])]
  public ?string $publishedAt = null;

  /**
   * @var array{id:string,name:string}|null
   */
  #[Groups(['maintenance_economic:read'])]
  public ?array $site = null;

  /**
   * @var array{id:string,name:string}|null
   */
  #[Groups(['maintenance_economic:read'])]
  public ?array $customer = null;

  #[Groups(['maintenance_economic:read'])]
  public string $snapshotState = '';

  #[Groups(['maintenance_economic:read'])]
  public bool $identityComplete = false;

  /**
   * @var list<array{id:string,name:?string,assetReference:?string,site:?array{id:string,name:string},customer:?array{id:string,name:string}}>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $equipment = [];

  /**
   * @param list<array{id:string,name:?string,assetReference:?string,site:?array{id:string,name:string},customer:?array{id:string,name:string}}> $financialEquipment minimal authorized financial identities
   */
  public static function fromContext(InterventionEconomicContext $context, array $financialEquipment = []): self
  {
    $output = new self();
    foreach (['id', 'number', 'name', 'type', 'status', 'site', 'customer', 'snapshotState', 'identityComplete'] as $field) {
      $output->{$field} = $context->{$field};
    }
    $output->publishedAt = $context->publishedAt?->format('c');
    $equipment = [];
    foreach ($context->workItems as $work) {
      if (null !== $work->equipmentId) {
        $equipment[$work->equipmentId] = ['id' => $work->equipmentId, 'name' => $work->equipmentIdentity?->name, 'assetReference' => $work->equipmentIdentity?->assetReference, 'site' => $work->site, 'customer' => $work->customer];
      }
    }
    foreach ($financialEquipment as $identity) {
      $key = $identity['id'];
      if (!isset($equipment[$key])) {
        $equipment[$key] = $identity;
      } elseif (($equipment[$key]['site']['id'] ?? null) !== ($identity['site']['id'] ?? null) || ($equipment[$key]['customer']['id'] ?? null) !== ($identity['customer']['id'] ?? null)) {
        $equipment[$key . ':' . ($identity['site']['id'] ?? '') . ':' . ($identity['customer']['id'] ?? '')] = $identity;
      }
    }
    $output->equipment = array_values($equipment);

    return $output;
  }
}
