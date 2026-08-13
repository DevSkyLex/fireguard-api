<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionAttachmentKind.
 *
 * Distinguishes a plain evidence file from the typed completion signature
 * (Phase 5d.2) captured when the responsible submits field work. At most one
 * `SIGNATURE` attachment may exist per intervention at a time — a second
 * signature upload replaces the first (see `AddInterventionAttachmentHandler`).
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionAttachmentKind: string
{
  case FILE = 'file';
  case SIGNATURE = 'signature';
}
