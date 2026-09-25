<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, Post};
use Workload\Presentation\Api\Dto\Input\{CapacityExceptionInput, CapacityWeekInput};
use Workload\Presentation\Api\Dto\Output\{CapacityChangeOutput, CapacityOutput};
use Workload\Presentation\Api\Operation\WorkloadOperations;
use Workload\Presentation\Api\Processor\CapacityProcessor;
use Workload\Presentation\Api\Provider\CapacityProvider;

/**
 * CapacityResource.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(shortName: 'WorkloadCapacity', operations: [
  new Get(name: WorkloadOperations::GET_SETTINGS, uriTemplate: '/organizations/{organizationId}/workload/settings', uriVariables: ['organizationId'], output: CapacityOutput::class, provider: CapacityProvider::class, security: self::SECURITY_ROLE_USER),
  new Post(name: WorkloadOperations::SAVE_SETTINGS, uriTemplate: '/organizations/{organizationId}/workload/settings', uriVariables: ['organizationId'], read: false, input: CapacityWeekInput::class, output: CapacityChangeOutput::class, processor: CapacityProcessor::class, status: 201, security: self::SECURITY_ROLE_USER),
  new Get(name: WorkloadOperations::GET_CAPACITY, uriTemplate: '/organizations/{organizationId}/workload/members/{memberId}/capacity', uriVariables: ['organizationId', 'memberId'], output: CapacityOutput::class, provider: CapacityProvider::class, security: self::SECURITY_ROLE_USER),
  new Post(name: WorkloadOperations::SAVE_CAPACITY, uriTemplate: '/organizations/{organizationId}/workload/members/{memberId}/capacity', uriVariables: ['organizationId', 'memberId'], read: false, input: CapacityWeekInput::class, output: CapacityChangeOutput::class, processor: CapacityProcessor::class, status: 201, security: self::SECURITY_ROLE_USER),
  new Get(name: WorkloadOperations::GET_EXCEPTIONS, uriTemplate: '/organizations/{organizationId}/workload/members/{memberId}/exceptions', uriVariables: ['organizationId', 'memberId'], output: CapacityOutput::class, provider: CapacityProvider::class, security: self::SECURITY_ROLE_USER),
  new Post(name: WorkloadOperations::CREATE_EXCEPTION, uriTemplate: '/organizations/{organizationId}/workload/members/{memberId}/exceptions', uriVariables: ['organizationId', 'memberId'], read: false, input: CapacityExceptionInput::class, output: CapacityChangeOutput::class, processor: CapacityProcessor::class, status: 201, security: self::SECURITY_ROLE_USER),
  new Delete(name: WorkloadOperations::CANCEL_EXCEPTION, uriTemplate: '/organizations/{organizationId}/workload/members/{memberId}/exceptions/{exceptionId}', uriVariables: ['organizationId', 'memberId', 'exceptionId'], read: false, input: false, output: false, processor: CapacityProcessor::class, status: 204, security: self::SECURITY_ROLE_USER),
])]
final class CapacityResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";
  // #endregion
}
