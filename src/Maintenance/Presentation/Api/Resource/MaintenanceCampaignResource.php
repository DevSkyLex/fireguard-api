<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use Maintenance\Presentation\Api\Dto\Input\GenerateInspectionCampaignInput;
use Maintenance\Presentation\Api\Dto\Output\GenerateInspectionCampaignOutput;
use Maintenance\Presentation\Api\Processor\GenerateInspectionCampaignProcessor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource MaintenanceCampaignResource.
 *
 * Generates an intervention draft ("inspection campaign") from an
 * organization's due/overdue maintenance schedules.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MaintenanceCampaign',
  operations: [
    new Post(
      uriTemplate: '/maintenance/campaigns',
      input: GenerateInspectionCampaignInput::class,
      output: GenerateInspectionCampaignOutput::class,
      processor: GenerateInspectionCampaignProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class MaintenanceCampaignResource
{
}
