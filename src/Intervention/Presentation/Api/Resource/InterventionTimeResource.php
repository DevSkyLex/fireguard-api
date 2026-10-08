<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, Patch, Post, QueryParameter};
use Intervention\Presentation\Api\Dto\Input\WriteTimeEntryInput;
use Intervention\Presentation\Api\Dto\Output\{TimeEntryHistoryOutput, TimeEntryOutput, TimeJournalOutput};
use Intervention\Presentation\Api\Operation\InterventionTimeOperations;
use Intervention\Presentation\Api\Processor\InterventionTimeProcessor;
use Intervention\Presentation\Api\Provider\InterventionTimeProvider;

/**
 * InterventionTimeResource.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(shortName: 'InterventionTime', normalizationContext: ['skip_null_values' => false], operations: [
  new Get(name: InterventionTimeOperations::GET, uriTemplate: '/intervention-work-items/{taskId}/time-entries/{entryId}', uriVariables: ['taskId', 'entryId'], output: TimeEntryOutput::class, provider: InterventionTimeProvider::class, security: self::SECURITY_ROLE_USER),
  new Get(name: InterventionTimeOperations::LIST, uriTemplate: '/intervention-work-items/{taskId}/time-entries', uriVariables: ['taskId'], output: TimeJournalOutput::class, provider: InterventionTimeProvider::class, security: self::SECURITY_ROLE_USER, parameters: [
    'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], castToArray: false, castToNativeType: false, constraints: []),
    'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30], castToArray: false, castToNativeType: false, constraints: []),
    'ownOnly' => new QueryParameter(schema: ['type' => 'boolean', 'default' => false], description: 'Limit entries and their total to the current member, including for time managers.', castToArray: false, castToNativeType: false, constraints: []),
  ]),
  new Get(name: InterventionTimeOperations::VERSIONS, uriTemplate: '/intervention-work-items/{taskId}/time-entries/{entryId}/versions', uriVariables: ['taskId', 'entryId'], output: TimeEntryHistoryOutput::class, provider: InterventionTimeProvider::class, security: self::SECURITY_ROLE_USER, parameters: [
    'beforeRevision' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1], description: 'Exclusive revision cursor; omitted for the newest retained revisions.', castToArray: false, castToNativeType: false, constraints: []),
    'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30], castToArray: false, castToNativeType: false, constraints: []),
  ]),
  new Post(name: InterventionTimeOperations::CREATE, uriTemplate: '/intervention-work-items/{taskId}/time-entries', uriVariables: ['taskId'], read: false, input: WriteTimeEntryInput::class, output: TimeEntryOutput::class, processor: InterventionTimeProcessor::class, status: 201, security: self::SECURITY_ROLE_USER),
  new Patch(name: InterventionTimeOperations::CORRECT, uriTemplate: '/intervention-work-items/{taskId}/time-entries/{entryId}', uriVariables: ['taskId', 'entryId'], read: false, input: WriteTimeEntryInput::class, output: TimeEntryOutput::class, processor: InterventionTimeProcessor::class, security: self::SECURITY_ROLE_USER),
  new Delete(name: InterventionTimeOperations::CANCEL, uriTemplate: '/intervention-work-items/{taskId}/time-entries/{entryId}', uriVariables: ['taskId', 'entryId'], read: false, input: false, output: false, processor: InterventionTimeProcessor::class, status: 204, security: self::SECURITY_ROLE_USER),
])]
final class InterventionTimeResource
{
  // #region Constants
  /**
   * Constant SECURITY_ROLE_USER
   */
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";
  // #endregion
}
