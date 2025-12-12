<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Exception\RoleNotFoundException;
use Authorization\Domain\ValueObject\RoleId;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Processor DeleteRoleProcessor
 * @final
 *
 * API Platform processor for deleting a role.
 *
 * @category Processor
 * @package Authorization\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteRoleProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * DeleteRoleProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleRepositoryPort $roleRepository The role repository.
   */
  public function __construct(
    private readonly RoleRepositoryPort $roleRepository
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the role deletion.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return void No return value.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $id = $uriVariables['id'] ?? null;

    if ($id === null) {
      throw RoleNotFoundException::withId(roleId: 'unknown');
    }

    $role = $this->roleRepository->findById(id: new RoleId(value: $id));

    if ($role === null) {
      throw RoleNotFoundException::withId(roleId: $id);
    }

    // Prevent deletion of system roles
    if ($role->isSystem()) {
      throw new BadRequestHttpException(
        message: 'System roles cannot be deleted.'
      );
    }

    // Delete
    $this->roleRepository->delete(role: $role);
  }
  //#endregion
}
