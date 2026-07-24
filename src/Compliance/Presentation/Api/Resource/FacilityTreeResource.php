<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use Compliance\Presentation\Api\Dto\Output\FacilityTreeOutput;
use Compliance\Presentation\Api\Operation\ComplianceOperations;
use Compliance\Presentation\Api\Provider\GetFacilityTreeProvider;

/**
 * Resource FacilityTreeResource.
 *
 * Read-only enriched facility hierarchy (Site -> Building -> Floor ->
 * Zone/Area): each node carries an equipment count and the SAME compliance
 * verdict + rate the compliance register exposes, batched by facility-id
 * list (never per node). Owned by Compliance — not Facility — so Facility's
 * own `/children`/`/descendants` endpoints stay byte-for-byte unchanged and
 * Facility keeps depending on nothing but its own repository port.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityTree',
  routePrefix: '/organizations',
  description: 'Enriched facility hierarchy (Site/Building/Floor/Zone) with per-node equipment count and compliance verdict/rate, batched by facility-id list. Requires organization.compliance.read.',
  operations: [
    new Get(
      name: ComplianceOperations::GET_FACILITY_TREE,
      uriTemplate: '/{organizationId}/facility-tree',
      input: false,
      output: FacilityTreeOutput::class,
      provider: GetFacilityTreeProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class FacilityTreeResource
{
}
