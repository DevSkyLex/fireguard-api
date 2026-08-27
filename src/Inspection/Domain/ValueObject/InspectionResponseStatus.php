<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

/**
 * ValueObject InspectionResponseStatus.
 *
 * The lifecycle of one canonical inspection response representation.
 *
 * `DRAFT` is what an intervention-scoped response carries while its
 * intervention is still being prepared: it may be edited and deleted.
 * `PUBLISHED` is the immutable record left behind once the intervention
 * publishes — and the default for a response created outside any
 * intervention, which is published on arrival.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InspectionResponseStatus: string
{
  case DRAFT = 'draft';
  case PUBLISHED = 'published';
}
