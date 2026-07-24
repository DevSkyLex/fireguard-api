<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, Post};
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Processor\Media\MediaProcessor;
use Equipment\Presentation\Api\Provider\Media\MediaProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource MediaResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Media',
  description: 'Multipart media attached to operational resources.',
  operations: [
    new Post(uriTemplate: '/media', deserialize: false, input: false, output: AttachmentOutput::class, processor: MediaProcessor::class, security: "is_granted('ROLE_USER')"),
    new Get(uriTemplate: '/media/{id}', output: AttachmentOutput::class, provider: MediaProvider::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/media/{id}', read: false, input: false, output: false, processor: MediaProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class MediaResource
{
}
