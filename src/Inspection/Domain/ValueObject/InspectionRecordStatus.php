<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/**
 * ValueObject InspectionRecordStatus.
 *
 * Whether an inspection row is a real, published record or the scratchpad an
 * intervention is still preparing. Orthogonal to {@see InspectionStatus},
 * which is the inspection's own lifecycle: a `DRAFT` **record** can carry any
 * inspection status, and a `PUBLISHED` one can be `draft`.
 *
 * The distinction is what `InspectionRepositoryPort::findPublishedById()`
 * enforces for the aggregate commands, and what decides — on the canonical
 * surface — whether a status change is validated against the lifecycle and
 * audited, or treated as a free-form scratchpad edit.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InspectionRecordStatus: string
{
  case DRAFT = 'draft';
  case PUBLISHED = 'published';
}
