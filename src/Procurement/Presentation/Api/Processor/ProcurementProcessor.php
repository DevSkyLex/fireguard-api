<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Procurement\Application\UseCase\Command\ManageProcurement\{ManageProcurementCommand, ManageProcurementResult};
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Presentation\Api\Dto\Input\{ChangePurchaseOrderInput, ChangeSupplierInput, CreatePurchaseOrderInput, CreateSupplierInput, IndividualizeReceiptInput, ReceivePurchaseOrderInput, ReturnProcurementReceiptInput};
use Procurement\Presentation\Api\Dto\Output\{ProcurementReceiptOutput, ProcurementReturnOutput, PurchaseOrderOutput, SupplierOutput};
use Procurement\Presentation\Api\Operation\ProcurementOperations;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function in_array;
use function is_object;
use function is_string;
use function preg_match;
use function property_exists;

/** Maps transport primitives and revisions into the only authorized mutation entry point.
 * @implements ProcessorInterface<CreateSupplierInput|CreatePurchaseOrderInput|ChangeSupplierInput|ChangePurchaseOrderInput|ReceivePurchaseOrderInput|IndividualizeReceiptInput|ReturnProcurementReceiptInput|null,SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput|ProcurementReturnOutput>
 */
final readonly class ProcurementProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables @param array<string,mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SupplierOutput|PurchaseOrderOutput|ProcurementReceiptOutput|ProcurementReturnOutput
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $action = match ($operation->getName()) {
      ProcurementOperations::CREATE_SUPPLIER => 'create_supplier',
      ProcurementOperations::CHANGE_SUPPLIER => 'change_supplier',
      ProcurementOperations::ARCHIVE_SUPPLIER => 'archive_supplier',
      ProcurementOperations::CREATE_ORDER => 'create_order',
      ProcurementOperations::CHANGE_ORDER => 'change_order',
      ProcurementOperations::PLACE_ORDER => 'order',
      ProcurementOperations::CANCEL_REMAINING => 'cancel_remaining',
      ProcurementOperations::RECEIVE => 'receive',
      ProcurementOperations::INDIVIDUALIZE => 'individualize',
      ProcurementOperations::RETURN => 'return',
      ProcurementOperations::RECONCILE_RETURN => 'reconcile_return',
      default => throw ProcurementException::invalid('Unknown procurement HTTP mutation.'),
    };
    $payload = [];
    $request = $this->requests->getCurrentRequest();
    if (!in_array($action, ['archive_supplier', 'order', 'cancel_remaining'], true)) {
      if (!is_object($data)) {
        throw ProcurementException::invalid('A procurement input is required.');
      }
      foreach ($request?->getPayload()->all() ?? [] as $field => $value) {
        if (!property_exists($data, $field)) {
          throw ProcurementException::invalid('Unknown procurement field.');
        }
        $payload[$field] = $data->{$field};
      }
    }
    $header = $request?->headers->get('If-Match');
    $revision = null === $header ? null : (1 === preg_match('/^"revision-(\\d+)"$/', $header, $matches) ? (int) $matches[1] : -1);
    /** @var ManageProcurementResult $result */
    $result = $this->commands->dispatch(new ManageProcurementCommand($actorId, $this->identifier($uriVariables, 'organizationId') ?? '', $action, $this->identifier($uriVariables, 'id'), $revision, $payload));

    return match ($result->kind) {
      'supplier' => SupplierOutput::fromProjection($result->data, $result->replayed),
      'order' => PurchaseOrderOutput::fromProjection($result->data, $result->replayed),
      'receipt' => ProcurementReceiptOutput::fromProjection($result->data, $result->replayed),
      'return' => ProcurementReturnOutput::fromProjection($result->data, $result->replayed),
      default => throw ProcurementException::invalid('Invalid procurement result.'),
    };
  }

  /**
   * @param array<string,mixed> $variables
   */
  private function identifier(array $variables, string $field): ?string
  {
    return is_string($variables[$field] ?? null) ? $variables[$field] : null;
  }
}
