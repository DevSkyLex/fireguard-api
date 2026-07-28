<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use Organization\Application\Service\OrganizationInvitationTokenHasher;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function hash;
use function strlen;

/**
 * Test OrganizationInvitationTokenHasherTest.
 *
 * Invitation tokens are bearer credentials: only their hash is stored, and
 * the plaintext is mailed once. A hasher that produced predictable tokens,
 * or that stopped hashing, would let anyone holding the database — or
 * guessing — accept someone else's invitation.
 *
 * @category Application Service Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationInvitationTokenHasher::class)]
final class OrganizationInvitationTokenHasherTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGenerateProducesA64CharacterHexToken(): void
  {
    $token = new OrganizationInvitationTokenHasher()->generate();

    self::assertSame(64, strlen($token), '32 random bytes must render as 64 hex characters.');
    self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
  }

  #[Test]
  public function testGenerateNeverRepeatsAToken(): void
  {
    $hasher = new OrganizationInvitationTokenHasher();

    $tokens = [];
    for ($i = 0; $i < 25; ++$i) {
      $tokens[$hasher->generate()] = true;
    }

    self::assertCount(25, $tokens, 'Every generated invitation token must be unique.');
  }

  #[Test]
  public function testHashIsDeterministicAndNeverReturnsThePlaintext(): void
  {
    $hasher = new OrganizationInvitationTokenHasher();
    $token = $hasher->generate();

    $hash = $hasher->hash($token);

    self::assertSame($hash, $hasher->hash($token), 'Hashing must be stable to allow look-up by token.');
    self::assertNotSame($token, $hash);
    self::assertSame(hash('sha256', $token), $hash);
  }

  #[Test]
  public function testDifferentTokensHashDifferently(): void
  {
    $hasher = new OrganizationInvitationTokenHasher();

    self::assertNotSame($hasher->hash('token-a'), $hasher->hash('token-b'));
  }
  // #endregion
}
