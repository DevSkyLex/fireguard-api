<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Doctrine\Attribute;

use Attribute;

/**
 * Attribute TenantFilterExempt.
 *
 * Marks a Doctrine record as out of reach of the
 * `tenant` SQL filter, even though it carries a
 * `tenantId` field.
 *
 * The filter is a data-scoping device for business
 * reads. Identity and authorization records must
 * resolve identically whatever tenant the current
 * request is scoped to: filtering them strips the
 * caller of its own grants and turns every
 * permission-gated endpoint into a 403. Records
 * carrying this attribute are scoped explicitly by
 * their repositories instead.
 *
 * @category Doctrine Attribute
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TenantFilterExempt
{
}
