<?php

declare(strict_types=1);

namespace Webhook\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record WebhookSubscriptionRecord.
 *
 * `organization_id` is a plain column (not an ORM association) with its
 * foreign key added directly in the migration, mirroring
 * `Import\Infrastructure\Persistence\Doctrine\Record\ImportJobRecord`'s
 * precedent.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'webhook_subscriptions')]
#[ORM\Index(name: 'idx_webhook_subscription_org_active', columns: ['organization_id', 'is_active'])]
class WebhookSubscriptionRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property url.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'url', type: 'text')]
  public string $url;

  /**
   * Property secretCiphertext.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'secret_ciphertext', type: 'text')]
  public string $secretCiphertext;

  /**
   * Property eventTypes.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[ORM\Column(name: 'event_types', type: 'json')]
  public array $eventTypes = [];

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_active', type: 'boolean')]
  public bool $isActive = true;

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'description', type: 'text', options: ['default' => ''])]
  public string $description = '';

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
