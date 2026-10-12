<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record FederatedAuthFlowRecord.
 *
 * One-time state and encrypted PKCE verifier for an external authorization.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'federated_auth_flows')]
#[ORM\Index(name: 'idx_federated_flow_expiry', columns: ['expires_at'])]
class FederatedAuthFlowRecord
{
  // #region Properties
  /**
   * Property stateHash
   */
  #[ORM\Id]
  #[ORM\Column(name: 'state_hash', type: 'string', length: 64)]
  public string $stateHash;

  /**
   * Property browserBindingHash
   */
  #[ORM\Column(name: 'browser_binding_hash', type: 'string', length: 64)]
  public string $browserBindingHash;

  /**
   * Property provider
   */
  #[ORM\Column(type: 'string', length: 20)]
  public string $provider;

  /**
   * Property intent
   */
  #[ORM\Column(type: 'string', length: 10)]
  public string $intent;

  /**
   * Property userId
   */
  #[ORM\Column(name: 'user_id', type: 'string', length: 36, nullable: true)]
  public ?string $userId = null;

  /**
   * Property codeVerifier
   */
  #[ORM\Column(name: 'code_verifier', type: 'text')]
  public string $codeVerifier;

  /**
   * Property redirectUri
   */
  #[ORM\Column(name: 'redirect_uri', type: 'string', length: 500)]
  public string $redirectUri;

  /**
   * Property returnUrl
   */
  #[ORM\Column(name: 'return_url', type: 'string', length: 500)]
  public string $returnUrl;

  /**
   * Property expiresAt
   */
  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  public DateTimeImmutable $expiresAt;

  /**
   * Property consumedAt
   */
  #[ORM\Column(name: 'consumed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $consumedAt = null;

  /**
   * Property nonceHash
   */
  #[ORM\Column(name: 'nonce_hash', type: 'string', length: 64, nullable: true)]
  public ?string $nonceHash = null;

  /**
   * Property stage. Durable remote/local completion stage.
   */
  #[ORM\Column(type: 'string', length: 24, options: ['default' => 'reserved'])]
  public string $stage = 'reserved';

  /**
   * Property exchangeStartedAt. Non-null reserved state means the remote outcome may be unknown.
   */
  #[ORM\Column(name: 'exchange_started_at', type: 'datetimetz_immutable', nullable: true)]
  public ?DateTimeImmutable $exchangeStartedAt = null;

  /**
   * Property verifiedProfile. Authenticated encrypted identity proof, never provider bearer credentials.
   */
  #[ORM\Column(name: 'verified_profile', type: 'text', nullable: true)]
  public ?string $verifiedProfile = null;

  /**
   * Property proofExpiresAt. Absolute deadline never extended by a callback replay.
   */
  #[ORM\Column(name: 'proof_expires_at', type: 'datetimetz_immutable', nullable: true)]
  public ?DateTimeImmutable $proofExpiresAt = null;

  /**
   * Property completedUserId. Account owner needed for fresh revision/availability replay checks.
   */
  #[ORM\Column(name: 'completed_user_id', type: 'string', length: 36, nullable: true)]
  public ?string $completedUserId = null;
  // #endregion

}
