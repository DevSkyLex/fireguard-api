<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use ApiPlatform\OpenApi\Model\Operation;
use Auth\Presentation\Api\Dto\Input\EmailOwnership\ConfirmEmailOwnershipInput;
use Auth\Presentation\Api\Dto\Output\EmailOwnership\{EmailOwnershipOutput, StartEmailOwnershipOutput};
use Auth\Presentation\Api\Operation\EmailOwnershipOperations;
use Auth\Presentation\Api\Processor\EmailOwnership\{ConfirmEmailOwnershipProcessor, StartEmailOwnershipProcessor};
use Auth\Presentation\Api\Provider\EmailOwnership\GetEmailOwnershipProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource EmailOwnershipResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EmailOwnership',
  operations: [
    new Get(
      name: EmailOwnershipOperations::GET,
      uriTemplate: '/auth/email-ownership',
      read: true,
      output: EmailOwnershipOutput::class,
      provider: GetEmailOwnershipProvider::class,
      openapi: new Operation(summary: 'Read explicit Fireguard mailbox ownership proof'),
    ),
    new Post(
      name: EmailOwnershipOperations::START,
      uriTemplate: '/auth/email-ownership/start',
      status: Response::HTTP_OK,
      read: false,
      input: false,
      output: StartEmailOwnershipOutput::class,
      processor: StartEmailOwnershipProcessor::class,
      openapi: new Operation(summary: 'Send a code to the authenticated account current email'),
    ),
    new Post(
      name: EmailOwnershipOperations::CONFIRM,
      uriTemplate: '/auth/email-ownership/confirm',
      status: Response::HTTP_OK,
      read: false,
      input: ConfirmEmailOwnershipInput::class,
      output: EmailOwnershipOutput::class,
      processor: ConfirmEmailOwnershipProcessor::class,
      openapi: new Operation(summary: 'Confirm current mailbox possession with a single-use email code'),
    ),
  ],
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => [EmailOwnershipOperations::READ]],
  denormalizationContext: ['groups' => [EmailOwnershipOperations::WRITE]],
)]
final class EmailOwnershipResource
{
}
