<?php

declare(strict_types=1);

namespace User\Presentation\Api\Operation;

/**
 * Service PresencePreferenceOperations.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresencePreferenceOperations
{
  /**
   * Constant GET
   */
  public const string GET = 'user_get_presence_preference';

  /**
   * Constant UPDATE
   */
  public const string UPDATE = 'user_update_presence_preference';

  /**
   * Constant SUBSCRIPTION
   */
  public const string SUBSCRIPTION = 'user_presence_preference_subscription';
}
