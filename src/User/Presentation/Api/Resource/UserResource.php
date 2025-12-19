<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use User\Presentation\Api\Dto\UserInput;
use User\Presentation\Api\Dto\UserOutput;
use User\Presentation\Api\Processor\CreateUserProcessor;
use User\Presentation\Api\Processor\DeleteUserProcessor;
use User\Presentation\Api\Processor\UpdateUserProcessor;
use User\Presentation\Api\Provider\ListUsersProvider;
use User\Presentation\Api\Provider\UserProvider;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * Resource UserResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
    shortName: 'User',
    operations: [
        new Post(
            name: 'create',
            uriTemplate: '/users',
            input: UserInput::class,
            output: UserOutput::class,
            processor: CreateUserProcessor::class,
            normalizationContext: ['groups' => [UserSerializationGroup::READ]],
            denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]]
        ),
        new Get(
            name: 'get',
            uriTemplate: '/users/{id}',
            input: false,
            output: UserOutput::class,
            provider: UserProvider::class,
            normalizationContext: ['groups' => [UserSerializationGroup::READ]]
        ),
        new GetCollection(
            name: 'list',
            uriTemplate: '/users',
            input: false,
            output: UserOutput::class,
            provider: ListUsersProvider::class,
            normalizationContext: ['groups' => [UserSerializationGroup::READ]]
        ),
        new Patch(
            name: 'update',
            uriTemplate: '/users/{id}',
            input: UserInput::class,
            output: UserOutput::class,
            processor: UpdateUserProcessor::class,
            normalizationContext: ['groups' => [UserSerializationGroup::READ]],
            denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]]
        ),
        new Put(
            name: 'replace',
            uriTemplate: '/users/{id}',
            input: UserInput::class,
            output: UserOutput::class,
            processor: UpdateUserProcessor::class,
            normalizationContext: ['groups' => [UserSerializationGroup::READ]],
            denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]]
        ),
        new Delete(
            name: 'delete',
            uriTemplate: '/users/{id}',
            input: false,
            output: false,
            processor: DeleteUserProcessor::class
        ),
    ]
)]
final class UserResource
{
}
