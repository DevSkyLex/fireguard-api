<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationJoin;

use DateTimeImmutable;

/**
 * Domain OrganizationDomain.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationDomain
{
  /**
   * @since 1.0.0
   *
   * @param string $id the domain identifier
   * @param string $organizationId the owning organization
   * @param string $domain the exact ASCII domain
   * @param string $dnsValue the unique public DNS challenge
   * @param string $status the proof state
   * @param ?DateTimeImmutable $verifiedAt last successful proof
   * @param ?DateTimeImmutable $lastCheckedAt last DNS attempt
   */
  public function __construct(public string $id, public string $organizationId, public string $domain, public string $dnsValue, public string $status = 'pending', public ?DateTimeImmutable $verifiedAt = null, public ?DateTimeImmutable $lastCheckedAt = null)
  {
  }

  /**
   * @since 1.0.0
   *
   * @return string the TXT record name
   */
  public function dnsName(): string
  {
    return '_fireguard-verification.' . $this->domain;
  }

  /**
   * Unknown DNS transport errors preserve the last successful proof for at most 48 hours.
   *
   * @since 1.0.0
   *
   * @param ?bool $present true for exact proof, false for authoritative absence, null for outage
   * @param DateTimeImmutable $now the check time
   */
  public function recordCheck(?bool $present, DateTimeImmutable $now): void
  {
    $this->lastCheckedAt = $now;
    if (true === $present) {
      $this->status = 'verified';
      $this->verifiedAt = $now;
    } elseif (false === $present || !$this->isUsable($now)) {
      $this->status = 'suspended';
    }
  }

  /**
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now current instant
   *
   * @return bool current proof validity
   */
  public function isUsable(DateTimeImmutable $now): bool
  {
    return 'verified' === $this->status && null !== $this->verifiedAt && $this->verifiedAt > $now->modify('-48 hours');
  }
}
