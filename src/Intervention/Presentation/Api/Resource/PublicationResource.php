<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use Intervention\Presentation\Api\Dto\Input\CreatePublicationInput;
use Intervention\Presentation\Api\Dto\Output\PublicationOutput;
use Intervention\Presentation\Api\Processor\PublicationProcessor;
use Intervention\Presentation\Api\Provider\PublicationProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource PublicationResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Publication',
  operations: [
    new Post(uriTemplate: '/publications', input: CreatePublicationInput::class, output: PublicationOutput::class, processor: PublicationProcessor::class, status: Response::HTTP_ACCEPTED, security: "is_granted('ROLE_USER')"),
    new Get(uriTemplate: '/publications/{id}', output: PublicationOutput::class, provider: PublicationProvider::class, security: "is_granted('ROLE_USER')"),
  ],
)]
final class PublicationResource
{
}
