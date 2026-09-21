<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Parameters};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Import\Presentation\Api\Dto\Output\ImportTemplateOutput;
use Import\Presentation\Api\Provider\ImportTemplateProvider;

/** Resource ImportTemplateResource. Organization-scoped server-owned CSV templates. */
#[ApiResource(shortName: 'ImportTemplate', operations: [
  new Get(
    name: 'get_import_template',
    uriTemplate: '/organizations/{organizationId}/import-templates/{kind}',
    output: ImportTemplateOutput::class,
    provider: ImportTemplateProvider::class,
    security: "is_granted('ROLE_USER')",
    strictQueryParameterValidation: true,
    parameters: new Parameters(),
    openapi: new Operation(
      tags: ['Import'],
      summary: 'Download the CSV template for an authorized import kind',
      responses: [
        200 => new Response(description: 'Filename, CSV content and media type'),
        403 => new Response(description: 'Missing write permission for this kind'),
        404 => new Response(description: 'Unknown kind or organization outside membership scope'),
      ],
    ),
  ),
])]
final class ImportTemplateResource
{
}
