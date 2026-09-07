<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Federated authentication operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedAuthOperations
{
  public const string PROVIDERS = 'federated_providers';

  public const string LOGIN_START = 'federated_login_start';

  public const string LOGIN_COMPLETE = 'federated_login_complete';

  public const string CONNECTIONS = 'federated_connections';

  public const string LINK_START = 'federated_link_start';

  public const string LINK_COMPLETE = 'federated_link_complete';

  public const string DISCONNECT = 'federated_disconnect';
}
