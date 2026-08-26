<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

/**
 * ValueObject EquipmentRecordStatus.
 *
 * Whether an equipment row is a real, published asset or the scratchpad an
 * intervention is still preparing. Orthogonal to {@see EquipmentStatus},
 * which is the asset's own lifecycle: a `DRAFT` **record** can carry any
 * equipment status, and a `PUBLISHED` one can be `in_stock`.
 *
 * On the canonical surface it decides whether a status change is validated
 * against the lifecycle, stamps `commissionedAt`, syncs the maintenance log
 * and reaches the audit ledger — or is treated as a free-form scratchpad
 * edit that does none of those.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum EquipmentRecordStatus: string
{
  case DRAFT = 'draft';
  case PUBLISHED = 'published';
}
