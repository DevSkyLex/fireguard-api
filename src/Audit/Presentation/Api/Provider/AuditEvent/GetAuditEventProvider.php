<?php

declare(strict_types=1);

namespace Audit\Presentation\Api\Provider\AuditEvent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Audit\Application\Contract\AuditEventView;
use Audit\Application\UseCase\Query\GetAuditEvent\GetAuditEventQuery;
use Audit\Presentation\Api\Dto\Output\AuditEvent\AuditEventOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

use function is_string;

/**
 * Provider GetAuditEventProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AuditEventOutput>
 */
final readonly class GetAuditEventProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetAuditEventProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  /**
   * Method provide.
   *
   * Provides a single audit event output by ID.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation metadata
   * @param array<string, mixed> $uriVariables URI variables
   * @param array<string, mixed> $context provider context
   *
   * @return AuditEventOutput the output DTO
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): AuditEventOutput
  {
    $eventId = $uriVariables['id'] ?? '';
    if (!is_string($eventId)) {
      $eventId = '';
    }

    /** @var AuditEventView $view */
    $view = $this->queryBus->ask(query: new GetAuditEventQuery(eventId: $eventId));

    $output = new AuditEventOutput();
    $output->id = $view->id;
    $output->action = $view->action;
    $output->actorType = $view->actorType;
    $output->actorId = $view->actorId;
    $output->actorEmail = $view->actorEmail;
    $output->actorEmailHash = $view->actorEmailHash;
    $output->subjectType = $view->subjectType;
    $output->subjectId = $view->subjectId;
    $output->clientId = $view->clientId;
    $output->tenantId = $view->tenantId;
    $output->ipAddress = $view->ipAddress;
    $output->ipHash = $view->ipHash;
    $output->userAgent = $view->userAgent;
    $output->metadata = $view->metadata;
    $output->occurredAt = $view->occurredAt;
    $output->recordedAt = $view->recordedAt;
    $output->chainId = $view->chainId;
    $output->sequence = $view->sequence;
    $output->prevHash = $view->prevHash;
    $output->eventHash = $view->eventHash;

    return $output;
  }
}
