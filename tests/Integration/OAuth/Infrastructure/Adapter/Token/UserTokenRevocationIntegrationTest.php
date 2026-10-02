<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\Adapter\Token;

use Doctrine\ORM\EntityManagerInterface;
use OAuth\Infrastructure\Adapter\Token\TokenRevocationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function is_int;
use function is_string;

/**
 * Class UserTokenRevocationIntegrationTest
 *
 * Verifies that credential revocation closes every stored OAuth grant for the account.
 *
 * @category Integration Test
 */
#[CoversClass(TokenRevocationAdapter::class)]
final class UserTokenRevocationIntegrationTest extends KernelTestCase
{
  // #region Methods
  /**
   * Method testRevokesAllUserGrantFamiliesWithoutAffectingOtherAccounts
   *
   * Includes refresh tokens anchored to already revoked access tokens and unused codes.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRevokesAllUserGrantFamiliesWithoutAffectingOtherAccounts(): void
  {
    self::bootKernel();
    $container = static::getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $connection = $entityManager->getConnection();
    foreach (['target', 'other'] as $owner) {
      foreach (['active', 'revoked', 'expired'] as $state) {
        $identifier = 'bulk-' . $owner . '-' . $state;
        $connection->insert('access_tokens', [
          'identifier' => $identifier,
          'client_identifier' => 'expired' === $state ? 'bulk-second-client' : 'bulk-client',
          'user_identifier' => 'bulk-' . $owner,
          'scopes' => '["READ"]',
          'expiry' => 'expired' === $state ? '2000-01-01 00:00:00' : '2099-01-01 00:00:00',
          'is_revoked' => 'revoked' === $state ? 1 : 0,
        ]);
        $connection->insert('refresh_tokens', [
          'identifier' => $identifier . '-refresh',
          'access_token_identifier' => $identifier,
          'client_identifier' => 'expired' === $state ? 'bulk-second-client' : 'bulk-client',
          'expiry' => '2099-01-01 00:00:00',
          'is_revoked' => 0,
        ]);
      }
      $connection->insert('auth_codes', [
        'identifier' => 'bulk-' . $owner . '-code',
        'client_identifier' => 'bulk-client',
        'user_identifier' => 'bulk-' . $owner,
        'scopes' => '["READ"]',
        'expiry' => '2099-01-01 00:00:00',
        'is_revoked' => 0,
      ]);
    }

    $connection->insert('access_tokens', ['identifier' => 'bulk-machine', 'client_identifier' => 'bulk-client', 'user_identifier' => null, 'scopes' => '["READ"]', 'expiry' => '2099-01-01 00:00:00', 'is_revoked' => 0]);

    /** @var TokenRevocationAdapter $adapter */
    $adapter = $container->get(TokenRevocationAdapter::class);
    $adapter->revokeAllUserTokens('bulk-target');

    foreach (['access_tokens', 'refresh_tokens', 'auth_codes'] as $table) {
      self::assertSame(0, $this->countValue($connection->fetchOne(
        'SELECT COUNT(*) FROM ' . $table . ' WHERE identifier LIKE ? AND is_revoked = false',
        ['bulk-target-%'],
      )), $table . ' must contain no usable target grants');
    }
    self::assertSame(2, $this->countValue($connection->fetchOne("SELECT COUNT(*) FROM access_tokens WHERE user_identifier = 'bulk-other' AND is_revoked = false")));
    self::assertSame(3, $this->countValue($connection->fetchOne("SELECT COUNT(*) FROM refresh_tokens WHERE identifier LIKE 'bulk-other-%' AND is_revoked = false")));
    self::assertSame(1, $this->countValue($connection->fetchOne("SELECT COUNT(*) FROM auth_codes WHERE user_identifier = 'bulk-other' AND is_revoked = false")));
    self::assertSame(1, $this->countValue($connection->fetchOne("SELECT COUNT(*) FROM access_tokens WHERE identifier = 'bulk-machine' AND is_revoked = false")));
  }

  /**
   * @param mixed $value the database count
   */
  private function countValue(mixed $value): int
  {
    self::assertTrue(is_int($value) || is_string($value));

    return (int) $value;
  }
  // #endregion
}
