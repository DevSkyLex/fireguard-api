<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use Authorization\Presentation\Api\Dto\PermissionInput;
use Authorization\Presentation\Api\Dto\PermissionOutput;
use Symfony\Component\Uid\Uuid;

/**
 * Processor CreatePermissionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<PermissionInput, PermissionOutput>
 */
final readonly class CreatePermissionProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CreatePermissionProcessor class.
   *
   * @since 1.0.0
   *
   * @param PermissionRepositoryPort $permissionRepository the permission repository
   */
  public function __construct(
    private readonly PermissionRepositoryPort $permissionRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the permission creation.
   *
   * @since 1.0.0
   *
   * @param PermissionInput      $data         the input data
   * @param Operation            $operation    the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context      the context
   *
   * @return PermissionOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PermissionOutput
  {
    /** @var PermissionInput $data */

    // Create the permission
    $permission = Permission::create(
      id: new PermissionId(value: Uuid::v7()->toRfc4122()),
      name: new PermissionName(value: $data->name),
      description: $data->description ?? ''
    );

    // Save
    $this->permissionRepository->save(permission: $permission);

    // Return output
    $output = new PermissionOutput();
    $output->id = $permission->id()->value;
    $output->name = $permission->name()->value;
    $output->description = $permission->description();
    $output->createdAt = $permission->createdAt()->format('Y-m-d H:i:s');

    return $output;
  }
  // #endregion
}
