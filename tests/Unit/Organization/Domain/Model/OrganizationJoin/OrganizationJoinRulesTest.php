<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Model\OrganizationJoin;

use DateTimeImmutable;
use Organization\Domain\Exception\{OrganizationJoinException, OrganizationJoinInputException};
use Organization\Domain\Model\OrganizationJoin\{OrganizationAccessPolicy, OrganizationDomain, OrganizationJoinRequest};
use Organization\Domain\ValueObject\{OrganizationJoinMode, OrganizationJoinPermissions};
use Organization\Infrastructure\Adapter\Native\OrganizationDomainProofAdapter;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Deterministic security boundary coverage for domain eligibility and request transitions.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinRulesTest extends TestCase
{
  #[Test]
  public function dnsOutageExpiresExactlyAtFortyEightHours(): void
  {
    $now = new DateTimeImmutable('2026-09-07T12:00:00Z');
    $proof = new OrganizationDomain('id', 'org', 'fireguard.example', 'proof');
    $proof->recordCheck(true, $now);
    $proof->recordCheck(null, $now->modify('+47 hours'));
    self::assertTrue($proof->isUsable($now->modify('+47 hours')));
    $proof->recordCheck(null, $now->modify('+48 hours'));
    self::assertSame('suspended', $proof->status);
    self::assertFalse($proof->isUsable($now->modify('+48 hours')));
  }

  #[Test]
  public function authoritativeAbsenceSuspendsImmediately(): void
  {
    $now = new DateTimeImmutable();
    $proof = new OrganizationDomain('id', 'org', 'fireguard.example', 'proof');
    $proof->recordCheck(true, $now);
    $proof->recordCheck(false, $now->modify('+1 second'));
    self::assertFalse($proof->isUsable($now->modify('+1 second')));
  }

  #[Test]
  public function automaticModeRequiresExplicitRole(): void
  {
    $policy = new OrganizationAccessPolicy('org');
    self::assertSame(OrganizationJoinMode::INVITATION_ONLY, $policy->mode);
    $this->expectException(OrganizationJoinInputException::class);
    $policy->configure(OrganizationJoinMode::AUTOMATIC, null);
  }

  #[Test]
  public function wildcardRoleCannotEscapeMemberCeiling(): void
  {
    self::assertFalse(OrganizationJoinPermissions::isSubset(['organization.*'], ['organization.members.read']));
    self::assertFalse(OrganizationJoinPermissions::isSubset(['*'], ['organization.*']));
    self::assertTrue(OrganizationJoinPermissions::isSubset(['organization.members.read'], ['organization.members.*']));
  }

  #[Test]
  public function requestRejectIsIdempotentButCannotLaterApprove(): void
  {
    $now = new DateTimeImmutable();
    $request = new OrganizationJoinRequest('id', 'org', 'user', 'a@corp.example', 'domain', $now, $now->modify('+30 days'));
    self::assertTrue($request->decide('rejected', $now));
    self::assertFalse($request->decide('rejected', $now));
    $this->expectException(OrganizationJoinException::class);
    $request->decide('approved', $now);
  }

  #[Test]
  public function expiredRequestCannotApprove(): void
  {
    $now = new DateTimeImmutable();
    $request = new OrganizationJoinRequest('id', 'org', 'user', 'a@corp.example', 'domain', $now->modify('-31 days'), $now->modify('-1 day'));
    self::assertSame('expired', $request->state($now));
    $this->expectException(OrganizationJoinException::class);
    $request->decide('approved', $now);
  }

  /**
   * @return iterable<string,array{string}>
   */
  public static function unsafeDomains(): iterable
  {
    foreach (['gmail.com', 'staff.gmail.com', 'outlook.com', 'mailinator.com', 'com', 'co.uk', 'github.io', 'user@company.com', 'https://company.com', '127.0.0.1', 'company.local/path'] as $domain) {
      yield $domain => [$domain];
    }
  }

  #[Test]
  #[DataProvider('unsafeDomains')]
  public function publicOrMalformedDomainsAreRejected(string $domain): void
  {
    $this->expectException(OrganizationJoinInputException::class);
    new OrganizationDomainProofAdapter()->normalize($domain);
  }

  #[Test]
  public function validCompanyDomainIsNormalizedAndChallengesAreUnique(): void
  {
    $adapter = new OrganizationDomainProofAdapter();
    self::assertSame('fireguard.example', $adapter->normalize('Fireguard.Example.'));
    self::assertNotSame($adapter->challenge(), $adapter->challenge());
    self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $adapter->identifier());
  }
}
