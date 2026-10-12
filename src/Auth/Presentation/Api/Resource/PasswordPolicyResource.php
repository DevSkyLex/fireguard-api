<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\Operation;
use Auth\Presentation\Api\Dto\Output\Password\PasswordPolicyOutput;
use Auth\Presentation\Api\Operation\PasswordPolicyOperations;
use Auth\Presentation\Api\Provider\Password\PasswordPolicyProvider;

/**
 * Resource PasswordPolicyResource.
 *
 * Publishes non-secret project requirements before a user creates credentials.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'PasswordPolicy',
  operations: [new Get(
    name: PasswordPolicyOperations::GET,
    uriTemplate: '/auth/password-policy',
    output: PasswordPolicyOutput::class,
    provider: PasswordPolicyProvider::class,
    normalizationContext: ['groups' => ['password_policy:read'], 'skip_null_values' => false],
    openapi: new Operation(tags: ['Authentication'], summary: 'Read the public password requirements'),
  )],
)]
final class PasswordPolicyResource
{
}
