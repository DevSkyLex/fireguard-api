<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ResolveCanonicalInspectionScope;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ResolveCanonicalInspectionScopeQuery.
 *
 * `GET /api/inspections` accepts two scoping filters and needs exactly one
 * organization out of them, BEFORE it may permission-check anything. Both
 * identifiers arrive already parsed: an IRI is transport.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveCanonicalInspectionScopeQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public ?string $organizationId = null,
    public ?string $interventionId = null,
  ) {
  }
  // #endregion
}
