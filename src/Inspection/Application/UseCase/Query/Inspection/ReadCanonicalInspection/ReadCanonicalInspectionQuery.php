<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ReadCanonicalInspectionQuery.
 *
 * The full read behind `GET /api/inspections/{id}`. Distinct from
 * `GetCanonicalInspectionQuery`, which projects only the five facts the
 * MUTATION gate needs — this one carries everything the wire contract shows.
 *
 * Deliberately unscoped by organization: the canonical route carries no
 * organization segment, so the organization to check against is the one on
 * the row itself. The gate that follows the read is what makes it safe.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ReadCanonicalInspectionQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $inspectionId,
  ) {
  }
  // #endregion
}
