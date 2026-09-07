<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationJoin;

use DateTimeImmutable;
use Organization\Domain\Exception\OrganizationJoinException;

/**
 * Domain OrganizationJoinRequest.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinRequest
{
  /**
   * @since 1.0.0
   *
   * @param string $id the request identifier
   * @param string $organizationId the requested organization
   * @param string $userId the applicant
   * @param string $email the proven current email at request time
   * @param string $domainId the proof used for eligibility
   * @param DateTimeImmutable $createdAt the submission timestamp
   * @param DateTimeImmutable $expiresAt the expiry
   * @param string $status the persisted request state
   * @param ?DateTimeImmutable $decidedAt the terminal transition timestamp
   */
  public function __construct(public string $id, public string $organizationId, public string $userId, public string $email, public string $domainId, public DateTimeImmutable $createdAt, public DateTimeImmutable $expiresAt, public string $status = 'pending', public ?DateTimeImmutable $decidedAt = null)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now current instant
   *
   * @return string effective state
   */
  public function state(DateTimeImmutable $now): string
  {
    return 'pending' === $this->status && $this->expiresAt <= $now ? 'expired' : $this->status;
  }

  /**
   * A terminal transition is idempotent only for the same decision.
   *
   * @since 1.0.0
   *
   * @param string $status requested terminal state
   * @param DateTimeImmutable $now decision time
   *
   * @return bool whether the request changed
   */
  public function decide(string $status, DateTimeImmutable $now): bool
  {
    if ($this->status === $status) {
      return false;
    }
    if ('pending' !== $this->state($now)) {
      throw new OrganizationJoinException('organization_join_request_not_pending');
    }
    $this->status = $status;
    $this->decidedAt = $now;

    return true;
  }
}
