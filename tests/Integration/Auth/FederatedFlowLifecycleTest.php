<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Auth\Application\Contract\Federation\{FederatedFlow, FederatedProfile, ProviderId};
use Auth\Application\Port\Outbound\Federation\FederatedFlowRepositoryPort;
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Auth\Infrastructure\Adapter\Idempotency\FederatedProofRetentionAdapter;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;

use function hash;

/**
 * Proves durable exchange uncertainty, verified-profile recovery and physical proof erasure.
 *
 * @category Integration Test
 */
#[SkipDatabaseRollback]
final class FederatedFlowLifecycleTest extends KernelTestCase
{
  use RootCommitDatabaseIsolationTrait;

  public function testReservedExchangeCannotBeBlindlyRetriedAfterAProcessLoss(): void
  {
    [$repository, $manager] = $this->fixture();
    $first = $repository->consume('original-state', 'browser-proof', ProviderId::from('oidc'), 'login');
    self::assertNotNull($first);
    self::assertNotNull($first->exchangeStartedAt);
    $manager->clear();
    $fresh = $repository->inspect('original-state', 'browser-proof', ProviderId::from('oidc'), 'login');
    self::assertNotNull($fresh);
    self::assertSame('reserved', $fresh->stage);
    self::assertNotNull($fresh->exchangeStartedAt);
    $this->expectException(FederatedAuthException::class);
    $this->expectExceptionMessage('outcome is unknown');
    $repository->consume('original-state', 'browser-proof', ProviderId::from('oidc'), 'login');
  }

  public function testVerifiedProfileCanResumeOnlyForTheOriginalBrowserAndExpiresPhysically(): void
  {
    [$repository, $manager] = $this->fixture();
    self::assertNull($repository->consume('original-state', 'different-browser', ProviderId::from('oidc'), 'login'));
    self::assertNull($repository->inspect('other-state', 'browser-proof', ProviderId::from('oidc'), 'login'));
    $reserved = $repository->consume('original-state', 'browser-proof', ProviderId::from('oidc'), 'login');
    self::assertNotNull($reserved);
    self::assertSame(hash('sha256', 'original-nonce'), $reserved->nonceHash);
    $profile = new FederatedProfile(ProviderId::from('oidc'), 'provider-subject', 'verified@example.test', true, 'Verified', 'Profile', 'https://issuer.example.test');
    $repository->recordVerified($reserved->stateHash, $profile);
    $ciphertext = $manager->getConnection()->fetchOne('SELECT verified_profile FROM federated_auth_flows WHERE state_hash = ?', [$reserved->stateHash]);
    self::assertIsString($ciphertext);
    self::assertStringNotContainsString($profile->email, $ciphertext);
    self::assertStringNotContainsString($profile->subject, $ciphertext);
    $manager->clear();
    $verified = $repository->consume('original-state', 'browser-proof', ProviderId::from('oidc'), 'login');
    self::assertNotNull($verified);
    self::assertSame('provider_verified', $verified->stage);
    self::assertEquals($profile, $verified->verifiedProfile);
    self::assertNotNull($verified->proofExpiresAt);
    $deadline = $verified->proofExpiresAt;
    $manager->getConnection()->transactional(static function () use ($repository, $reserved): void { $repository->markCompleted($reserved->stateHash, 'f00e8400-e29b-41d4-a716-4466554c0801'); });
    $completed = $repository->inspect('original-state', 'browser-proof', ProviderId::from('oidc'), 'login');
    self::assertNotNull($completed);
    self::assertSame('completed', $completed->stage);
    self::assertEquals($deadline, $completed->proofExpiresAt);
    self::assertSame('', $completed->codeVerifier);
    self::assertNull($completed->verifiedProfile);
    self::assertNull($repository->consume('original-state', 'browser-proof', ProviderId::from('oidc'), 'login'));
    self::assertSame(1, new FederatedProofRetentionAdapter($manager)->eraseExpiredResults($deadline));
    self::assertNull($manager->getConnection()->fetchOne('SELECT verified_profile FROM federated_auth_flows WHERE state_hash = ?', [$reserved->stateHash]));
    self::assertSame('completed', $manager->getConnection()->fetchOne('SELECT stage FROM federated_auth_flows WHERE state_hash = ?', [$reserved->stateHash]));
    $manager->getConnection()->executeStatement('UPDATE federated_auth_flows SET proof_expires_at = ? WHERE state_hash = ?', [new DateTimeImmutable('-1 second')->format('Y-m-d H:i:sP'), $reserved->stateHash]);
    $expired = $repository->inspect('original-state', 'browser-proof', ProviderId::from('oidc'), 'login');
    self::assertNotNull($expired);
    self::assertSame('completed', $expired->stage);
    self::assertNull($expired->verifiedProfile);
    self::assertSame('', $expired->codeVerifier);
  }

  /**
   * @return array{FederatedFlowRepositoryPort,EntityManagerInterface} real owning persistence
   */
  private function fixture(): array
  {
    static::bootKernel();
    $manager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    $repository = static::getContainer()->get(FederatedFlowRepositoryPort::class);
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    self::assertInstanceOf(FederatedFlowRepositoryPort::class, $repository);
    $repository->save(new FederatedFlow(hash('sha256', 'original-state'), hash('sha256', 'browser-proof'), ProviderId::from('oidc'), 'login', null, 'original-pkce-verifier', 'https://app.example.test/callback', '/', new DateTimeImmutable('+10 minutes'), hash('sha256', 'original-nonce')));

    return [$repository, $manager];
  }
}
