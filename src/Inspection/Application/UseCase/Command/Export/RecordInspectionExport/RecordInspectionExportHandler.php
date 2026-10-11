<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Export\RecordInspectionExport;

use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/** Persists export audit intent on the main outbox before acknowledging the export.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordInspectionExportHandler implements CommandHandler
{
  public function __construct(private TransactionManagerPort $transactions, private EventDispatcherPort $events, private OrganizationAuthorizationPort $authorization)
  {
  }

  public function __invoke(RecordInspectionExportCommand $command): void
  {
    $decision = $this->authorization->resolveAccess($command->event->actorUserId, $command->event->organizationId, 'organization.inspection.read');
    if ($decision->isOutsideScope()) {
      throw new InspectionNotFoundException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing organization.inspection.read permission.');
    }
    $this->transactions->transactional(fn () => $this->events->dispatch($command->event));
  }
}
