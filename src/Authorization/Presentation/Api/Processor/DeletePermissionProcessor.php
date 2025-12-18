<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\ValueObject\PermissionId;

/**
 * Processor DeletePermissionProcessor
 * @final
 *
 * API Platform processor for deleting a permission.
 *
 * @category Processor
 * @package Authorization\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeletePermissionProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * DeletePermissionProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionRepositoryPort $permissionRepository The permission repository.
   */
  public function __construct(
    private readonly PermissionRepositoryPort $permissionRepository
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the permission deletion.
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

    if (!is_string($id)) {
      throw PermissionNotFoundException::withId(permissionId: 'unknown');
    }

    $permission = $this->permissionRepository->findById(id: new PermissionId(value: $id));

    if ($permission === null) {
      throw PermissionNotFoundException::withId(permissionId: $id);
    }

    // Delete
    $this->permissionRepository->delete(permission: $permission);
  }
  //#endregion
}
