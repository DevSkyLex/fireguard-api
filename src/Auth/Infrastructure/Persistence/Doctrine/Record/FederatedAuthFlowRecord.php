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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'federated_auth_flows')]
#[ORM\Index(name: 'idx_federated_flow_expiry', columns: ['expires_at'])]
class FederatedAuthFlowRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'state_hash', type: 'string', length: 64)]
  public string $stateHash;

  #[ORM\Column(name: 'browser_binding_hash', type: 'string', length: 64)]
  public string $browserBindingHash;

  #[ORM\Column(type: 'string', length: 20)]
  public string $provider;

  #[ORM\Column(type: 'string', length: 10)]
  public string $intent;

  #[ORM\Column(name: 'user_id', type: 'string', length: 36, nullable: true)]
  public ?string $userId = null;

  #[ORM\Column(name: 'code_verifier', type: 'text')]
  public string $codeVerifier;

  #[ORM\Column(name: 'redirect_uri', type: 'string', length: 500)]
  public string $redirectUri;

  #[ORM\Column(name: 'return_url', type: 'string', length: 500)]
  public string $returnUrl;

  #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
  public DateTimeImmutable $expiresAt;

  #[ORM\Column(name: 'consumed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $consumedAt = null;
}
