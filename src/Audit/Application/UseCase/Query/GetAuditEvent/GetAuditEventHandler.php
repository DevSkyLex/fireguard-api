<?php

declare(strict_types=1);

namespace Audit\Application\UseCase\Query\GetAuditEvent;

use Audit\Application\Contract\AuditEventView;
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Handler GetAuditEventHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetAuditEventHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetAuditEventHandler class.
   *
   * @since 1.0.0
   *
   * @param AuditEventRepositoryPort $repository the audit repository
   */
  public function __construct(
    private AuditEventRepositoryPort $repository,
  ) {
  }
  // #endregion

  /**
   * Method __invoke.
   *
   * Resolves a single audit event by ID.
   *
   * @since 1.0.0
   *
   * @param GetAuditEventQuery $query the query to handle
   *
   * @throws EntityNotFoundException
   *
   * @return AuditEventView the event view
   */
  public function __invoke(GetAuditEventQuery $query): AuditEventView
  {
    $event = $this->repository->findById(id: $query->eventId);

    if (null === $event) {
      throw EntityNotFoundException::forId(
        entityType: 'AuditEvent',
        id: $query->eventId,
      );
    }

    return $event;
  }
}
