<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\{ManageMaintenanceExportCommand,ManageMaintenanceExportResult};
use MaintenanceExport\Domain\Exception\MaintenanceExportException;
use MaintenanceExport\Presentation\Api\Dto\Input\{AdjustMaintenanceExportInput,ConfirmMaintenanceExportInput,CreateMaintenanceExportInput,WriteMaintenanceExportReferenceInput};
use MaintenanceExport\Presentation\Api\Dto\Output\{MaintenanceExportOutput,MaintenanceExportReferenceOutput};
use MaintenanceExport\Presentation\Api\Operation\MaintenanceExportOperations;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_object;
use function is_string;
use function preg_match;
use function property_exists;

/**
 * Class MaintenanceExportProcessor
 * Translates declarations into the only command entry point.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<CreateMaintenanceExportInput|AdjustMaintenanceExportInput|ConfirmMaintenanceExportInput|WriteMaintenanceExportReferenceInput|null,MaintenanceExportOutput|MaintenanceExportReferenceOutput>
 */
final readonly class MaintenanceExportProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @return void
   */
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   *
   * @param array<string,mixed> $uriVariables scoped route
   * @param array<string,mixed> $context serializer context
   *
   * @return MaintenanceExportOutput|MaintenanceExportReferenceOutput authorized metadata
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceExportOutput|MaintenanceExportReferenceOutput
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $action = match($operation->getName()) {
      MaintenanceExportOperations::CREATE => 'create',MaintenanceExportOperations::ADJUST => 'adjustment',MaintenanceExportOperations::CONFIRM => 'confirm',MaintenanceExportOperations::WRITE_REFERENCE => 'reference',default => throw MaintenanceExportException::invalid('Unknown export mutation.')
    };
    if (!is_object($data)) {
      throw MaintenanceExportException::invalid('An export input is required.');
    }
    $request = $this->requests->getCurrentRequest();
    $payload = [];
    foreach ($request?->getPayload()->all() ?? [] as $field => $value) {
      if (!property_exists($data, $field)) {
        throw MaintenanceExportException::invalid('Unknown export input field.');
      }
      $payload[$field] = $data->{$field};
    }
    if ('reference' === $action) {
      $payload['resourceType'] = $this->string($uriVariables, 'resourceType');
      $payload['resourceId'] = $this->string($uriVariables, 'resourceId');
    }
    $header = $request?->headers->get('If-Match');
    $revision = $this->revision($header);
    $id = 'reference' === $action ? $payload['resourceId'] : $this->optionalId($uriVariables);
    /**
     * @var ManageMaintenanceExportResult $result
     */
    $result = $this->commands->dispatch(new ManageMaintenanceExportCommand($actor, $this->string($uriVariables, 'organizationId'), $action, $id, $revision, $payload));

    return 'reference' === $result->kind ? MaintenanceExportReferenceOutput::fromProjection($result->data, $result->replayed) : MaintenanceExportOutput::fromProjection($result->data, $result->replayed);
  }

  /**
   * Method revision
   *
   * Missing and malformed preconditions retain their distinct command values.
   *
   * @access private
   *
   * @param string|null $header supplied If-Match value
   *
   * @return int|null parsed revision, null when absent or minus one when malformed
   */
  private function revision(?string $header): ?int
  {
    if (null === $header) {
      return null;
    }

    return 1 === preg_match('/^"revision-(\d+)"$/', $header, $matches) ? (int) $matches[1] : -1;
  }

  /**
   * Method optionalId
   *
   * @access private
   *
   * @param array<string,mixed> $variables scoped route
   *
   * @return string|null supplied document identifier
   */
  private function optionalId(array $variables): ?string
  {
    return is_string($variables['id'] ?? null) ? $variables['id'] : null;
  }

  /**
   * Method string
   *
   * @param array<string,mixed> $data route variables
   *
   * @return string scoped route primitive
   */
  private function string(array $data, string $field): string
  {
    return is_string($data[$field] ?? null) ? $data[$field] : '';
  }
  // #endregion
}
