<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\ResolveInspectionResponseScope;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ResolveInspectionResponseScopeQuery.
 *
 * `GET /api/inspection-responses` accepts three mutually redundant scoping
 * filters and needs exactly one organization out of them, BEFORE it may
 * permission-check anything. This resolves that organization; the caller
 * gates on the answer and only then asks for the page.
 *
 * All three identifiers arrive already parsed: an IRI is transport.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionResponseScopeQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public ?string $organizationId = null,
    public ?string $interventionId = null,
    public ?string $inspectionId = null,
  ) {
  }
  // #endregion
}
