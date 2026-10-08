<?php

declare(strict_types=1);

namespace Procurement\Application\Service;

use Procurement\Application\Contract\ProcurementOperationState;
use Procurement\Application\Port\Outbound\Persistence\ProcurementRepositoryPort;
use Procurement\Application\UseCase\Command\ManageProcurement\{ManageProcurementCommand, ManageProcurementResult};
use Procurement\Domain\Exception\ProcurementException;

use function array_is_list;
use function hash;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function ksort;

use const JSON_THROW_ON_ERROR;

/**
 * Class ProcurementOperationReplay
 *
 * Preserves retained operation identities and their original serialized fingerprints.
 * Callers hold the organization's main transaction lock throughout replay and recording.
 *
 * @category Service
 */
final readonly class ProcurementOperationReplay
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param ProcurementRepositoryPort $repository the repository value
   * @param ProcurementProjection $projection the projection value
   * @param ProcurementInput $input the input value
   *
   * @return void
   */
  public function __construct(private ProcurementRepositoryPort $repository, private ProcurementProjection $projection, private ProcurementInput $input)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method creationReplay
   *
   * Resolves an existing creation before another resource or event is produced.
   *
   * @access public
   *
   * @param ManageProcurementCommand $command the command value
   * @param bool $finance the finance value
   *
   * @return ?ManageProcurementResult
   */
  public function creationReplay(ManageProcurementCommand $command, bool $finance): ?ManageProcurementResult
  {
    $operationId = $this->creationOperationId($command);
    if (null === $operationId) {
      return null;
    }

    return $this->replay($command, $operationId, $command->action, $this->creationFingerprint($command), $finance);
  }

  /**
   * Method recordCreation
   *
   * Retains the created resource identity in the same transaction as its resource.
   *
   * @access public
   *
   * @param ManageProcurementCommand $command the command value
   * @param ManageProcurementResult $result the result value
   *
   * @return void
   */
  public function recordCreation(ManageProcurementCommand $command, ManageProcurementResult $result): void
  {
    $operationId = $this->creationOperationId($command);
    if (null === $operationId) {
      return;
    }
    $resourceId = $result->data['id'] ?? null;
    if (!is_string($resourceId)) {
      throw ProcurementException::conflict('The created resource has no durable identity.');
    }
    $this->repository->saveOperation(new ProcurementOperationState($command->organizationId, $operationId, $command->action, $this->creationFingerprint($command), $resourceId, $command->payload + ['actorId' => $command->actorId]));
  }

  /**
   * Method operationId
   *
   * Validates the stable client operation identity.
   *
   * @access public
   *
   * @param ManageProcurementCommand $command the command value
   *
   * @return string
   */
  public function operationId(ManageProcurementCommand $command): string
  {
    return $this->input->uuid($this->input->text($command->payload, 'clientOperationId'));
  }

  /**
   * Method replay
   *
   * Rejects a changed declaration before resolving its retained resource projection.
   *
   * @access public
   *
   * @param ManageProcurementCommand $command the command value
   * @param string $operationId the operationId value
   * @param string $kind the kind value
   * @param string $fingerprint the fingerprint value
   * @param bool $finance the finance value
   *
   * @return ?ManageProcurementResult
   */
  public function replay(ManageProcurementCommand $command, string $operationId, string $kind, string $fingerprint, bool $finance): ?ManageProcurementResult
  {
    $operation = $this->repository->operation($command->organizationId, $operationId);
    if (null === $operation) {
      return null;
    }
    if ($operation->kind !== $kind || $operation->fingerprint !== $fingerprint) {
      throw ProcurementException::conflict('The offline operation identifier already belongs to a different declaration.');
    }

    return match ($kind) {
      'create_supplier' => $this->supplierReplay($command, $operation),
      'create_order' => $this->orderReplay($command, $operation, $finance),
      'reconcile_return' => $this->returnReplay($command, $operation),
      default => $this->receiptReplay($command, $operation, $finance),
    };
  }

  /**
   * Method fingerprint
   *
   * Keeps the exact original PHP JSON serialization used by retained operation hashes.
   *
   * @access public
   *
   * @param array<array-key,mixed> $values
   *
   * @return string
   */
  public function fingerprint(array $values): string
  {
    return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
  }

  /**
   * Method supplierReplay
   *
   * Projects the retained supplier without repeating creation effects.
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param ProcurementOperationState $operation the operation value
   *
   * @return ManageProcurementResult
   */
  private function supplierReplay(ManageProcurementCommand $command, ProcurementOperationState $operation): ManageProcurementResult
  {
    $supplier = $this->repository->supplier($command->organizationId, $operation->receiptId) ?? throw ProcurementException::conflict('The saved creation has no supplier.');

    return new ManageProcurementResult('supplier', $this->projection->supplier($supplier), true);
  }

  /**
   * Method orderReplay
   *
   * Projects the retained order with the caller's financial visibility.
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param ProcurementOperationState $operation the operation value
   * @param bool $finance the finance value
   *
   * @return ManageProcurementResult
   */
  private function orderReplay(ManageProcurementCommand $command, ProcurementOperationState $operation, bool $finance): ManageProcurementResult
  {
    $order = $this->repository->order($command->organizationId, $operation->receiptId) ?? throw ProcurementException::conflict('The saved creation has no order.');

    return new ManageProcurementResult('order', $this->projection->order($order, $finance), true);
  }

  /**
   * Method returnReplay
   *
   * Projects the retained reconciliation declaration without repeating stock effects.
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param ProcurementOperationState $operation the operation value
   *
   * @return ManageProcurementResult
   */
  private function returnReplay(ManageProcurementCommand $command, ProcurementOperationState $operation): ManageProcurementResult
  {
    $returnId = $operation->declaration['returnId'] ?? null;
    $return = is_string($returnId) ? $this->repository->returnDeclaration($command->organizationId, $returnId) : null;

    return new ManageProcurementResult('return', $this->projection->returnDeclaration($return ?? throw ProcurementException::conflict('The retained return declaration is missing.')), true);
  }

  /**
   * Method receiptReplay
   *
   * Projects the retained physical receipt without another stock or equipment effect.
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   * @param ProcurementOperationState $operation the operation value
   * @param bool $finance the finance value
   *
   * @return ManageProcurementResult
   */
  private function receiptReplay(ManageProcurementCommand $command, ProcurementOperationState $operation, bool $finance): ManageProcurementResult
  {
    $receipt = $this->repository->receipt($command->organizationId, $operation->receiptId) ?? throw ProcurementException::conflict('The saved physical operation has no receipt.');

    return new ManageProcurementResult('receipt', $this->projection->receipt($receipt, $finance), true);
  }

  /**
   * Method creationOperationId
   *
   * Retains compatibility with older creation callers that omit an operation key.
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   *
   * @return ?string
   */
  private function creationOperationId(ManageProcurementCommand $command): ?string
  {
    if (!in_array($command->action, ['create_supplier', 'create_order'], true) || null === ($command->payload['clientOperationId'] ?? null)) {
      return null;
    }

    return $this->operationId($command);
  }

  /**
   * Method creationFingerprint
   *
   * Excludes the transport operation key from the submitted creation declaration.
   *
   * @access private
   *
   * @param ManageProcurementCommand $command the command value
   *
   * @return string
   */
  private function creationFingerprint(ManageProcurementCommand $command): string
  {
    $payload = $command->payload;
    unset($payload['clientOperationId']);

    return $this->fingerprint($this->creationPayload($payload));
  }

  /**
   * Method creationPayload
   *
   * Keeps object-key order and UUID spelling from changing a retained creation's identity.
   * Generated resource and line UUIDs are deliberately absent from the submitted fingerprint.
   *
   * @access private
   *
   * @param array<array-key,mixed> $payload the submitted creation values
   *
   * @return array<array-key,mixed> the deterministic submitted values
   */
  private function creationPayload(array $payload): array
  {
    foreach ($payload as $field => $value) {
      if (is_array($value)) {
        $payload[$field] = $this->creationPayload($value);
      } elseif (is_string($value) && in_array($field, ['id', 'supplierId', 'partId'], true)) {
        $payload[$field] = $this->input->uuid($value);
      }
    }
    if (!array_is_list($payload)) {
      ksort($payload);
    }

    return $payload;
  }
  // #endregion
}
