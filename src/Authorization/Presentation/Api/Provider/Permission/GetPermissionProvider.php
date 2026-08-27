<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Provider\Permission;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Authorization\Application\UseCase\Query\Permission\GetPermission\{GetPermissionQuery, GetPermissionResult};
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

use function assert;
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
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
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
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return PermissionOutput|null the permission output or null if not found
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PermissionOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      return null;
    }

    $query = new GetPermissionQuery(permissionId: $id);

    $result = $this->queryBus->ask(query: $query);

    assert($result instanceof GetPermissionResult);

    $output = new PermissionOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->description = $result->description;
    $output->createdAt = $result->createdAt;

    return $output;
  }
  // #endregion
}
