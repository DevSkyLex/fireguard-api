<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Facility\Presentation\Api\Dto\Input\Attachment\SetFacilityAttachmentCalibrationInput;
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Operation\FacilityOperations;
use Facility\Presentation\Api\Processor\Attachment\SetFacilityAttachmentCalibrationProcessor;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;

/**
 * Resource FacilityAttachmentCalibrationResource.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityPlanCalibration',
  operations: [new Put(
    name: FacilityOperations::SET_FACILITY_ATTACHMENT_CALIBRATION,
    uriTemplate: '/facility-attachments/{id}/calibration',
    status: 200,
    read: false,
    input: SetFacilityAttachmentCalibrationInput::class,
    output: FacilityAttachmentOutput::class,
    processor: SetFacilityAttachmentCalibrationProcessor::class,
    security: "is_granted('ROLE_USER')",
    denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
    normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
    openapi: new Operation(
      tags: ['Facility'],
      summary: 'Calibrate a floor plan in its building',
      description: 'Sets a uniform metric scale, rotation and offset on the original floor plan image. Send calibration null to clear it. Requires organization.facilities.write and a matching attachment revision.',
      parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string'], example: '"revision-1"')],
      responses: [200 => new Response(description: 'Calibration saved'), 400 => new Response(description: 'Invalid calibration'), 403 => new Response(description: 'Missing permission'), 404 => new Response(description: 'Attachment not found'), 409 => new Response(description: 'Attachment is not a floor plan'), 412 => new Response(description: 'Stale revision'), 428 => new Response(description: 'If-Match is required')],
    ),
  )],
)]
final class FacilityAttachmentCalibrationResource
{
}
