<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection};
use Mission\Presentation\Api\Dto\Output\ReferencePackOutput;
use Mission\Presentation\Api\Provider\ReferencePackProvider;

/**
 * Resource ReferencePackResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'ReferencePack',
  operations: [
    new GetCollection(uriTemplate: '/reference-packs', output: ReferencePackOutput::class, provider: ReferencePackProvider::class, paginationEnabled: false, security: "is_granted('ROLE_USER')"),
    new Get(uriTemplate: '/reference-packs/{id}', output: ReferencePackOutput::class, provider: ReferencePackProvider::class, security: "is_granted('ROLE_USER')"),
  ],
)]
final class ReferencePackResource
{
}
