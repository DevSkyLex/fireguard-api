<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Operation;

/**
 * Federated authentication operation names.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FederatedAuthOperations
{
  /**
   * Constant PROVIDERS
   */
  public const string PROVIDERS = 'federated_providers';

  /**
   * Constant LOGIN_START
   */
  public const string LOGIN_START = 'federated_login_start';

  /**
   * Constant LOGIN_COMPLETE
   */
  public const string LOGIN_COMPLETE = 'federated_login_complete';

  /**
   * Constant CONNECTIONS
   */
  public const string CONNECTIONS = 'federated_connections';

  /**
   * Constant LINK_START
   */
  public const string LINK_START = 'federated_link_start';

  /**
   * Constant LINK_COMPLETE
   */
  public const string LINK_COMPLETE = 'federated_link_complete';

  /**
   * Constant DISCONNECT
   */
  public const string DISCONNECT = 'federated_disconnect';
}
