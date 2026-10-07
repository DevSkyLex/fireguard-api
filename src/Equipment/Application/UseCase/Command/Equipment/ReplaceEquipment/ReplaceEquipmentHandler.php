<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment;

use Equipment\Application\Contract\Replacement\EquipmentReplacementReceipt;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\{EquipmentReplacementRepositoryPort, FacilityValidationPort};
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentReplacementConflictException};
use Equipment\Domain\Model\Replacement\{EquipmentReplacement, ReplacementEquipment};
use Equipment\Domain\ValueObject\EquipmentIdentity;
use InvalidArgumentException;
use LogicException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;

use function array_map;
use function hash;
use function json_encode;
use function usort;

use const JSON_THROW_ON_ERROR;

/**
 * Class ReplaceEquipmentHandler
 *
 * Journals replacement and optional asset creation in one main transaction before any replay can repeat effects.
 *
 * @category UseCase
 */
final readonly class ReplaceEquipmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EquipmentReplacementRepositoryPort $replacements the main replacement persistence port
   * @param TransactionManagerPort $transactionManager the main transaction
   * @param EquipmentMaintenanceLogSynchronizerPort $maintenance the lifecycle log owner
   * @param CommandBusPort $commandBus dispatches quota-enforced asset creation
   * @param FacilityValidationPort $facilities validates the target assignment
   *
   * @return void
   */
  public function __construct(
    private EquipmentReplacementRepositoryPort $replacements,
    private TransactionManagerPort $transactionManager,
    private EquipmentMaintenanceLogSynchronizerPort $maintenance,
    private CommandBusPort $commandBus,
    private FacilityValidationPort $facilities,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param ReplaceEquipmentCommand $command the replacement identity and payload
   *
   * @return ReplaceEquipmentResult the original durable result
   */
  public function __invoke(ReplaceEquipmentCommand $command): ReplaceEquipmentResult
  {
    if ((null === $command->successorEquipmentId) === (null === $command->successor)) {
      throw EquipmentReplacementConflictException::because('Provide exactly one existing successor or new successor description.');
    }
    $payloadHash = self::payloadHash($command);

    return $this->transactionManager->transactional(function () use ($command, $payloadHash): ReplaceEquipmentResult {
      $receipt = $this->replacements->findReceiptForUpdate($command->organizationId, $command->clientOperationId);
      if (null !== $receipt) {
        if ($receipt->payloadHash !== $payloadHash) {
          throw EquipmentReplacementConflictException::because('This client operation has already been used with another replacement payload.');
        }

        return new ReplaceEquipmentResult(
          $receipt->predecessorEquipmentId,
          $receipt->successorEquipmentId,
          $receipt->clientOperationId,
          true,
        );
      }

      $ids = [$command->equipmentId];
      if (null !== $command->successorEquipmentId) {
        $ids[] = $command->successorEquipmentId;
      }
      $candidates = $this->replacements->lockEquipment($command->organizationId, $ids);
      $predecessor = $candidates[$command->equipmentId] ?? throw EquipmentNotFoundException::withId($command->equipmentId);
      $predecessor->assertReplaceable();
      if (null !== $predecessor->facilityId) {
        try {
          $this->facilities->assertFacilityIsAssignable($predecessor->facilityId, $command->organizationId);
        } catch (InvalidArgumentException $exception) {
          throw EquipmentReplacementConflictException::because($exception->getMessage());
        }
      }
      $successorId = $command->successorEquipmentId;
      if (null === $successorId) {
        $successorId = $this->createSuccessor($command, $predecessor);
        $candidates = $this->replacements->lockEquipment($command->organizationId, [$successorId]);
      }
      $successor = $candidates[$successorId] ?? throw EquipmentNotFoundException::withId($successorId);
      $replacement = new EquipmentReplacement($predecessor, $successor);
      $receipt = new EquipmentReplacementReceipt(
        $command->organizationId,
        $command->clientOperationId,
        $predecessor->id,
        $successor->id,
        $payloadHash,
      );
      $this->maintenance->syncForStatusTransition($predecessor->id, $command->organizationId, $predecessor->status, 'decommissioned');
      $this->replacements->save($replacement, $receipt);

      return new ReplaceEquipmentResult($predecessor->id, $successor->id, $command->clientOperationId, false);
    });
  }

  /**
   * Method createSuccessor
   *
   * Uses the ordinary creation use case inside the owning main transaction.
   *
   * @access private
   *
   * @param ReplaceEquipmentCommand $command the new asset fields
   * @param ReplacementEquipment $predecessor the locked original asset
   *
   * @return string the created successor identifier
   */
  private function createSuccessor(ReplaceEquipmentCommand $command, ReplacementEquipment $predecessor): string
  {
    $successor = $command->successor ?? throw new LogicException('New successor fields are unavailable.');
    $identity = EquipmentIdentity::fromValues($successor->name, $successor->assetCode, $successor->criticality, $successor->technicalProperties);
    $result = $this->commandBus->dispatch(new CreateEquipmentCommand(
      organizationId: $command->organizationId,
      type: $successor->type,
      subType: $successor->subType,
      brand: $successor->brand,
      model: $successor->model,
      serialNumber: $successor->serialNumber,
      locationLabel: $successor->locationLabel,
      facilityId: $predecessor->facilityId,
      name: $identity->name,
      assetCode: $identity->assetCode,
      criticality: $identity->criticality,
      technicalProperties: $identity->technicalProperties,
    ));
    if (!$result instanceof CreateEquipmentResult) {
      throw new LogicException('Equipment creation did not return its typed result.');
    }

    return $result->equipmentId;
  }

  /**
   * Method payloadHash
   *
   * Property order and an omitted optional unit do not change the replay identity.
   *
   * @access private
   *
   * @param ReplaceEquipmentCommand $command the requested replacement
   *
   * @return string the canonical request fingerprint
   */
  private static function payloadHash(ReplaceEquipmentCommand $command): string
  {
    $successor = $command->successor;
    $properties = null === $successor ? [] : $successor->technicalProperties;
    $properties = array_map(static fn (array $property): array => [
      'key' => $property['key'], 'value' => $property['value'], 'unit' => $property['unit'] ?? null,
    ], $properties);
    usort($properties, static fn (array $left, array $right): int => $left['key'] <=> $right['key']);
    $description = null === $successor ? null : [
      'type' => $successor->type, 'subType' => $successor->subType, 'brand' => $successor->brand,
      'model' => $successor->model, 'serialNumber' => $successor->serialNumber,
      'locationLabel' => $successor->locationLabel, 'name' => $successor->name,
      'assetCode' => $successor->assetCode, 'criticality' => $successor->criticality, 'technicalProperties' => $properties,
    ];

    return hash('sha256', json_encode([
      'predecessorEquipmentId' => $command->equipmentId,
      'successorEquipmentId' => $command->successorEquipmentId,
      'successor' => $description,
    ], JSON_THROW_ON_ERROR));
  }
  // #endregion
}
