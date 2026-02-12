<?php

declare(strict_types=1);

namespace Notification\Application\Contract\Notification;

/**
 * Enum NotificationChannel.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum NotificationChannel: string
{
  case EMAIL = 'email';
  case MERCURE = 'mercure';
}
