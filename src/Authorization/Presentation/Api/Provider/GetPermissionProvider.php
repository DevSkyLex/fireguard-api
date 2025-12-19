<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Presentation\Api\Dto\PermissionOutput;

use function is_string;

/**
 * Provider GetPermissionProvider.
 *
 * Provides the permission output.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PermissionOutput>
 */
final readonly class GetPermissionProvider implements ProviderInterface
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * GetPermissionProvider class.
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
     * Method provide
     * {@inheritDoc}
     *
     * Provides the permission output.
     *
     * @since 1.0.0
     *
     * @param Operation            $operation    the operation
     * @param array<string, mixed> $uriVariables the URI variables
     * @param array<string, mixed> $context      the context
     *
     * @return PermissionOutput|null the permission output or null if not found
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PermissionOutput
    {
        $id = $uriVariables['id'] ?? null;

        if (!is_string($id)) {
            return null;
        }

        $permission = $this->permissionRepository->findById(id: new PermissionId(value: $id));

        if (null === $permission) {
            return null;
        }

        $output = new PermissionOutput();
        $output->id = $permission->id()->value;
        $output->name = $permission->name()->value;
        $output->description = $permission->description();
        $output->createdAt = $permission->createdAt()->format('Y-m-d H:i:s');

        return $output;
    }
    // #endregion
}
