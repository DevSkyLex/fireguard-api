<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use Auth\Presentation\Api\Dto\Input\PasswordChange\ConfirmPasswordChangeInput;
use Auth\Presentation\Api\Dto\Output\PasswordChange\{ConfirmPasswordChangeOutput, RequestPasswordChangeOutput};
use Auth\Presentation\Api\Operation\PasswordSetupOperations;
use Auth\Presentation\Api\Processor\Federation\{ConfirmPasswordSetupProcessor, RequestPasswordSetupProcessor};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource PasswordSetupResource.
 *
 * Declares OTP-protected first password setup for federated-only users.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'PasswordSetup',
  operations: [
    new Post(
      name: PasswordSetupOperations::REQUEST,
      uriTemplate: '/auth/password/setup',
      status: Response::HTTP_OK,
      read: false,
      input: false,
      output: RequestPasswordChangeOutput::class,
      processor: RequestPasswordSetupProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      security: "is_granted('ROLE_USER')",
    ),
    new Post(
      name: PasswordSetupOperations::CONFIRM,
      uriTemplate: '/auth/password/setup/confirm',
      status: Response::HTTP_OK,
      read: false,
      input: ConfirmPasswordChangeInput::class,
      output: ConfirmPasswordChangeOutput::class,
      processor: ConfirmPasswordSetupProcessor::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::TOKEN_READ]],
      denormalizationContext: ['groups' => [AuthSerializationGroup::PASSWORD_CHANGE_WRITE]],
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class PasswordSetupResource
{
}
