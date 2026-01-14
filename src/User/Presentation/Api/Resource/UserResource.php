<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use User\Presentation\Api\Dto\Input\User\UserInput;
use User\Presentation\Api\Dto\Output\User\UserOutput;
use User\Presentation\Api\Operation\UserOperations;
use User\Presentation\Api\Processor\User\{CreateUserProcessor, DeleteUserProcessor, UpdateUserProcessor};
use User\Presentation\Api\Provider\User\{ListUsersProvider, UserProvider};
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
      name: UserOperations::CREATE,
      uriTemplate: '/users',
      input: UserInput::class,
      output: UserOutput::class,
      processor: CreateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]],
    ),
    new Get(
      name: UserOperations::GET,
      uriTemplate: '/users/{id}',
      input: false,
      output: UserOutput::class,
      provider: UserProvider::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
    ),
    new GetCollection(
      name: UserOperations::LIST,
      uriTemplate: '/users',
      input: false,
      output: UserOutput::class,
      provider: ListUsersProvider::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
    ),
    new Patch(
      name: UserOperations::UPDATE,
      uriTemplate: '/users/{id}',
      input: UserInput::class,
      output: UserOutput::class,
      processor: UpdateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]],
    ),
    new Put(
      name: UserOperations::REPLACE,
      uriTemplate: '/users/{id}',
      input: UserInput::class,
      output: UserOutput::class,
      processor: UpdateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]],
    ),
    new Delete(
      name: UserOperations::DELETE,
      uriTemplate: '/users/{id}',
      input: false,
      output: false,
      processor: DeleteUserProcessor::class,
    ),
  ],
)]
final class UserResource
{
}
