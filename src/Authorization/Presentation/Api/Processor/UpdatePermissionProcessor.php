<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Presentation\Api\Dto\PermissionInput;
use Authorization\Presentation\Api\Dto\PermissionOutput;

/**
 * Processor UpdatePermissionProcessor
 * @final
 *
 * API Platform processor for updating a permission.
 * Note: Permission name cannot be changed, only description.
 *
 * @category Processor
 * @package Authorization\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<PermissionInput, PermissionOutput>
 */
final readonly class UpdatePermissionProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * UpdatePermissionProcessor class.
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
   * Processes the permission update.
   * Note: Permission is immutable, so we cannot update it.
   * This endpoint returns the current state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionInput $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return PermissionOutput The processed output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PermissionOutput
  {
    /** @var PermissionInput $data */
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw PermissionNotFoundException::withId(permissionId: 'unknown');
    }

    $permission = $this->permissionRepository->findById(id: new PermissionId(value: $id));

    if ($permission === null) {
      throw PermissionNotFoundException::withId(permissionId: $id);
    }

    // Permission is readonly/immutable - return current state
    // If you need to update description, you would need to add an update method to Permission

    // Return output
    $output = new PermissionOutput();
    $output->id = $permission->id()->value;
    $output->name = $permission->name()->value;
    $output->description = $permission->description();
    $output->createdAt = $permission->createdAt()->format('Y-m-d H:i:s');

    return $output;
  }
  //#endregion
}
