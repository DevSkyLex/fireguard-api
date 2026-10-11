<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Export\RecordInterventionExport;

use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/** Persists export audit intent on the main outbox before acknowledging the export.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordInterventionExportHandler implements CommandHandler
{
  public function __construct(private TransactionManagerPort $transactions, private EventDispatcherPort $events, private OrganizationAuthorizationPort $authorization)
  {
  }

  public function __invoke(RecordInterventionExportCommand $command): void
  {
    $decision = $this->authorization->resolveAccess($command->event->actorUserId, $command->event->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw new InterventionNotFoundException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }
    $this->transactions->transactional(fn () => $this->events->dispatch($command->event));
  }
}
