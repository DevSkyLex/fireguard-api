<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * ValueObject FacilityRecordStatus.
 *
 * Whether a facility row is a real, published site or the scratchpad an
 * intervention is still preparing. Orthogonal to {@see FacilityStatus},
 * which is the facility's own lifecycle: a `DRAFT` **record** can be
 * `archived`, and a `PUBLISHED` one can be `active`.
 *
 * On the canonical surface it decides whether the hierarchy and archival
 * rules apply and whether the mutation reaches the audit ledger — or is
 * treated as a free-form scratchpad edit that does neither.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum FacilityRecordStatus: string
{
  case DRAFT = 'draft';
  case PUBLISHED = 'published';
}
