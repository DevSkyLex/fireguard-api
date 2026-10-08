<?php

declare(strict_types=1);

namespace Procurement\Application\Service;

use Equipment\Application\Port\Inbound\EquipmentReserveReceiptPort;
use Inventory\Application\Contract\Directory\InventoryPartDescriptor;
use Inventory\Application\Port\Inbound\InventoryPartDirectoryPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\ValueObject\{ProcurementGoodsIdentity, ProcurementLine};
use Shared\Application\Port\Outbound\UuidGeneratorPort;

use function array_is_list;
use function array_key_exists;
use function array_unique;
use function array_values;
use function count;
use function is_array;
use function is_string;

/**
 * Class ProcurementDraftLines
 *
 * Prepares catalog snapshots and enforces explicit financial patch authority.
 *
 * @category Service
 */
final readonly class ProcurementDraftLines
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param InventoryPartDirectoryPort $parts the parts value
   * @param EquipmentReserveReceiptPort $equipment the equipment value
   * @param UuidGeneratorPort $ids the ids value
   * @param ProcurementInput $input the input value
   *
   * @return void
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private InventoryPartDirectoryPort $parts,
    private EquipmentReserveReceiptPort $equipment,
    private UuidGeneratorPort $ids,
    private ProcurementInput $input,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method prepare
   *
   * Resolves one scoped catalog batch before preparing all replacement lines.
   *
   * @access public
   *
   * @param ManageProcurementCommand $command the scoped mutation
   * @param mixed $input the submitted replacement lines
   * @param list<ProcurementLine> $existing the retained draft snapshots
   *
   * @return list<ProcurementLine> the validated replacements
   */
  public function prepare(ManageProcurementCommand $command, mixed $input, array $existing): array
  {
    if (!is_array($input) || !array_is_list($input) || count($input) > 100) {
      throw ProcurementException::invalid('Order lines must be a bounded list.');
    }
    $known = [];
    foreach ($existing as $line) {
      $known[$line->id] = $line;
    }
    $descriptors = $this->describeParts($command->organizationId, $input);
    $lines = [];
    foreach ($input as $fields) {
      if (!is_array($fields)) {
        throw ProcurementException::invalid('An order line must be an object.');
      }
      /** @var array<string,mixed> $fields */
      $lines[] = $this->prepareLine($command, $fields, $known, $descriptors);
    }

    return $lines;
  }

  /**
   * Method describeParts
   *
   * @access private
   *
   * @param string $organizationId the owning organization
   * @param list<mixed> $lines the submitted lines
   *
   * @return array<string,InventoryPartDescriptor> the scoped catalog snapshots
   */
  private function describeParts(string $organizationId, array $lines): array
  {
    $partIds = [];
    foreach ($lines as $row) {
      if (is_array($row) && 'part' === ($row['kind'] ?? null) && is_string($row['partId'] ?? null)) {
        $partIds[] = $this->input->uuid($row['partId']);
      }
    }

    return $this->parts->describeMany($organizationId, array_values(array_unique($partIds)));
  }

  /**
   * Method prepareLine
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the scoped mutation
   * @param array<string,mixed> $fields the submitted line
   * @param array<string,ProcurementLine> $known the retained lines indexed by identity
   * @param array<string,InventoryPartDescriptor> $descriptors the scoped catalog snapshots
   *
   * @return ProcurementLine the validated replacement snapshot
   */
  private function prepareLine(ManageProcurementCommand $command, array $fields, array $known, array $descriptors): ProcurementLine
  {
    $this->input->fields($fields, ['id', 'kind', 'partId', 'typeCode', 'identityTemplate', 'quantity', 'unitCost']);
    $id = array_key_exists('id', $fields) ? $this->input->uuid($this->input->text($fields, 'id')) : $this->ids->generate();
    $kind = $this->input->text($fields, 'kind');
    $partId = $this->input->optionalText($fields, 'partId');
    $partId = null === $partId ? null : $this->input->uuid($partId);
    $typeCode = $this->input->optionalText($fields, 'typeCode');
    $descriptor = null === $partId ? null : ($descriptors[$partId] ?? null);
    $this->assertCatalogIdentity($command->organizationId, $kind, $typeCode, $descriptor);
    $unitCost = $this->unitCost($command, $fields, $known[$id] ?? null);
    $identity = $fields['identityTemplate'] ?? [];
    if (!is_array($identity) || (!empty($identity) && array_is_list($identity))) {
      throw ProcurementException::invalid('Equipment identity template must be an object.');
    }
    /** @var array<string,mixed> $identity */
    if ('equipment_to_individualize' === $kind) {
      $identity = $this->input->equipmentTemplate($identity);
    }

    return ProcurementLine::create($id, new ProcurementGoodsIdentity($kind, $partId, $typeCode, $identity, $descriptor?->code, $descriptor?->label, $descriptor?->unit), $this->input->quantity($fields['quantity'] ?? null), $unitCost);
  }

  /**
   * Method assertCatalogIdentity
   *
   * @access private
   *
   * @param string $organizationId the owning organization
   * @param string $kind the submitted line kind
   * @param ?string $typeCode the declared equipment type
   * @param ?InventoryPartDescriptor $descriptor the scoped part identity
   *
   * @return void
   */
  private function assertCatalogIdentity(string $organizationId, string $kind, ?string $typeCode, ?InventoryPartDescriptor $descriptor): void
  {
    if ('part' === $kind && (null === $descriptor || $descriptor->archived)) {
      throw ProcurementException::notFound();
    }
    if ('equipment_to_individualize' === $kind && (null === $typeCode || !$this->equipment->supportsType($organizationId, $typeCode))) {
      throw ProcurementException::notFound();
    }
  }

  /**
   * Method unitCost
   *
   * Omitted prices retain the existing cost; explicitly clearing a price requires authority.
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the scoped mutation
   * @param array<string,mixed> $fields the submitted line
   * @param ?ProcurementLine $existing the retained snapshot, if any
   *
   * @return ?string the exact price or authorized unknown value
   */
  private function unitCost(ManageProcurementCommand $command, array $fields, ?ProcurementLine $existing): ?string
  {
    if (!array_key_exists('unitCost', $fields)) {
      return $existing?->unitCost;
    }
    $decision = $this->authorization->resolveAccess($command->actorId, $command->organizationId, 'organization.maintenance_cost.manage');
    if (OrganizationAccessDecision::GRANTED !== $decision) {
      throw OrganizationAccessDecision::OUTSIDE_SCOPE === $decision ? ProcurementException::notFound() : ProcurementException::denied();
    }

    return $this->input->optionalText($fields, 'unitCost');
  }
  // #endregion
}
