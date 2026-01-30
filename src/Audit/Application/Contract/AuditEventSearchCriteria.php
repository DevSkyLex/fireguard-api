<?php

declare(strict_types=1);

namespace Audit\Application\Contract;

use DateTimeImmutable;

/**
 * Criteria AuditEventSearchCriteria.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuditEventSearchCriteria
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuditEventSearchCriteria class.
   *
   * @since 1.0.0
   *
   * @param string|null $action filter by action
   * @param string|null $actorType filter by actor type
   * @param string|null $actorId filter by actor ID
   * @param string|null $subjectType filter by subject type
   * @param string|null $subjectId filter by subject ID
   * @param string|null $clientId filter by client ID
   * @param string|null $tenantId filter by tenant ID
   * @param string|null $ipHash filter by IP hash
   * @param string|null $actorEmailHash filter by actor email hash
   * @param DateTimeImmutable|null $from lower bound (inclusive)
   * @param DateTimeImmutable|null $to upper bound (inclusive)
   */
  public function __construct(
    public ?string $action = null,
    public ?string $actorType = null,
    public ?string $actorId = null,
    public ?string $subjectType = null,
    public ?string $subjectId = null,
    public ?string $clientId = null,
    public ?string $tenantId = null,
    public ?string $ipHash = null,
    public ?string $actorEmailHash = null,
    public ?DateTimeImmutable $from = null,
    public ?DateTimeImmutable $to = null,
  ) {
  }
}
