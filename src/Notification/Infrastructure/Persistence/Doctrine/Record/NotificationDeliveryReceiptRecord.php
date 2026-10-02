<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record NotificationDeliveryReceiptRecord.
 *
 * Retains the stable inbox identity and acknowledged channel outcomes.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'notification_delivery_receipts')]
class NotificationDeliveryReceiptRecord
{
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 64)]
  public string $id;

  #[ORM\Column(name: 'notification_id', type: 'string', length: 36)]
  public string $notificationId;

  /**
   * @var array<string, string>
   */
  #[ORM\Column(name: 'channel_status', type: 'json', options: ['jsonb' => true])]
  public array $channelStatus = [];
}
