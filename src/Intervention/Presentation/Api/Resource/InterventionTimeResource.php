<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, Patch, Post};
use Intervention\Presentation\Api\Dto\Input\WriteTimeEntryInput;
use Intervention\Presentation\Api\Dto\Output\{TimeEntryOutput, TimeJournalOutput};
use Intervention\Presentation\Api\Operation\InterventionTimeOperations;
use Intervention\Presentation\Api\Processor\InterventionTimeProcessor;
use Intervention\Presentation\Api\Provider\InterventionTimeProvider;

/**
 * InterventionTimeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(shortName: 'InterventionTime', operations: [
  new Get(name: InterventionTimeOperations::LIST, uriTemplate: '/intervention-work-items/{taskId}/time-entries', uriVariables: ['taskId'], output: TimeJournalOutput::class, provider: InterventionTimeProvider::class, security: self::SECURITY_ROLE_USER),
  new Post(name: InterventionTimeOperations::CREATE, uriTemplate: '/intervention-work-items/{taskId}/time-entries', uriVariables: ['taskId'], read: false, input: WriteTimeEntryInput::class, output: TimeEntryOutput::class, processor: InterventionTimeProcessor::class, status: 201, security: self::SECURITY_ROLE_USER),
  new Patch(name: InterventionTimeOperations::CORRECT, uriTemplate: '/intervention-work-items/{taskId}/time-entries/{entryId}', uriVariables: ['taskId', 'entryId'], read: false, input: WriteTimeEntryInput::class, output: TimeEntryOutput::class, processor: InterventionTimeProcessor::class, security: self::SECURITY_ROLE_USER),
  new Delete(name: InterventionTimeOperations::CANCEL, uriTemplate: '/intervention-work-items/{taskId}/time-entries/{entryId}', uriVariables: ['taskId', 'entryId'], read: false, input: false, output: false, processor: InterventionTimeProcessor::class, status: 204, security: self::SECURITY_ROLE_USER),
])]
final class InterventionTimeResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";
  // #endregion
}
