<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Operation;

/**
 * Service PresenceOperations.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresenceOperations
{
  public const string PING = 'messaging_ping_presence';

  public const string GET = 'messaging_get_presence';

  public const string SUBSCRIPTION = 'messaging_presence_subscription';
}
