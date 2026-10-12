<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Defuse\Crypto\Crypto;
use Doctrine\DBAL\Connection;
use OAuth\Application\Contract\Client\ClientLifecycleProof;
use OAuth\Application\Contract\Token\TokenRevocationProof;
use OAuth\Application\Port\Inbound\{ClientLifecycleProofPort, TokenRevocationProofPort};
use OAuth\Application\Port\Outbound\Token\JwtParserPort;
use OAuth\Application\UseCase\Command\Token\RevokeToken\{RevokeTokenCommand, RevokeTokenResult};
use OAuth\Infrastructure\Adapter\Idempotency\{TokenRevocationIdempotencyProofAdapter, TokenRevocationProofAdapter};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, HttpIdempotencyPrincipal};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPrincipalPort, OrdinaryHttpReplayAuthorizationPort};
use Shared\Infrastructure\Idempotency\HmacIdempotencyFingerprintAdapter;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function base64_encode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * Pins cryptographic-before-lookup ordering and current owner proof on empty-result replay.
 *
 * @category Adapter Tests
 */
final class TokenRevocationIdempotencyProofAdapterTest extends TestCase
{
  #[Test]
  public function unverifiedJwtClaimsNeverDriveStoredGrantOrOwnerLookups(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::never())->method('fetchAssociative');
    $jwt = $this->createMock(JwtParserPort::class);
    $jwt->expects(self::once())->method('validate')->with('untrusted.token.claims')->willReturn(false);
    $jwt->expects(self::never())->method('parse');
    $clients = $this->createMock(ClientLifecycleProofPort::class);
    $clients->expects(self::never())->method('current');
    $users = $this->createMock(UserSecurityRevisionPort::class);
    $users->expects(self::never())->method('current');
    $proof = new TokenRevocationProofAdapter($connection, $jwt, $clients, $users, str_repeat('k', 32))->prove('untrusted.token.claims', 'access_token');

    self::assertSame('unknown', $proof->kind);
    self::assertNull($proof->tokenId);
  }

  #[Test]
  public function verifiedJwtIdentifierUsesOnlyTheFreshNativeStoredClientAndOwner(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')->with(self::anything(), ['verified-token'])->willReturn(['identifier' => 'verified-token', 'client_identifier' => 'native-client', 'user_identifier' => null, 'is_revoked' => true]);
    $jwt = $this->createMock(JwtParserPort::class);
    $jwt->expects(self::once())->method('validate')->willReturn(true);
    $jwt->expects(self::once())->method('parse')->willReturn(['jti' => 'verified-token', 'sub' => 'untrusted-owner-claim-is-not-used']);
    $clients = $this->createMock(ClientLifecycleProofPort::class);
    $clients->expects(self::once())->method('current')->with('native-client')->willReturn(new ClientLifecycleProof('native-client', 8, true, false, 'fp'));
    $users = $this->createMock(UserSecurityRevisionPort::class);
    $users->expects(self::never())->method('current');
    $proof = new TokenRevocationProofAdapter($connection, $jwt, $clients, $users, str_repeat('k', 32))->prove('signed-token', 'access_token');

    self::assertSame('native-client', $proof->clientId);
    self::assertSame(8, $proof->clientRevision);
    self::assertTrue($proof->revoked);
  }

  #[Test]
  public function authenticatedRefreshCiphertextPinsTheNativeClientBeforeReadingOwnerFacts(): void
  {
    $key = str_repeat('k', 32);
    $token = Crypto::encryptWithPassword(json_encode(['refresh_token_id' => 'verified-refresh', 'client_id' => 'native-client', 'user_id' => null, 'expire_time' => 1], JSON_THROW_ON_ERROR), $key);
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')->with(self::anything(), ['verified-refresh'])->willReturn(['identifier' => 'verified-refresh', 'client_identifier' => 'native-client', 'user_identifier' => null, 'is_revoked' => false]);
    $jwt = $this->createMock(JwtParserPort::class);
    $jwt->expects(self::never())->method('validate');
    $clients = $this->createMock(ClientLifecycleProofPort::class);
    $clients->expects(self::once())->method('current')->with('native-client')->willReturn(new ClientLifecycleProof('native-client', 9, true, false, 'fp'));
    $users = $this->createMock(UserSecurityRevisionPort::class);
    $users->expects(self::never())->method('current');

    $proof = new TokenRevocationProofAdapter($connection, $jwt, $clients, $users, $key)->prove($token, 'refresh_token');

    self::assertSame('refresh_token', $proof->kind);
    self::assertSame('verified-refresh', $proof->tokenId);
    self::assertSame(9, $proof->clientRevision);
  }

  #[Test]
  public function refreshCiphertextWithAnotherEncryptionKeyNeverDrivesGrantLookups(): void
  {
    $token = Crypto::encryptWithPassword('{"refresh_token_id":"forged-id","client_id":"forged-client"}', str_repeat('x', 32));
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::never())->method('fetchAssociative');
    $jwt = $this->createMock(JwtParserPort::class);
    $jwt->expects(self::once())->method('validate')->willReturn(false);
    $jwt->expects(self::never())->method('parse');
    $clients = $this->createMock(ClientLifecycleProofPort::class);
    $clients->expects(self::never())->method('current');
    $users = $this->createMock(UserSecurityRevisionPort::class);
    $users->expects(self::never())->method('current');

    self::assertSame('unknown', new TokenRevocationProofAdapter($connection, $jwt, $clients, $users, str_repeat('k', 32))->prove($token, 'refresh_token')->kind);
  }

  #[Test]
  public function revocationKeepsItsLogicalScopeAndInputStableAfterTheGrantIsRevoked(): void
  {
    $current = new TokenRevocationProof('access_token', 'token', 'client', 'user', 2, 4);
    $native = $this->createStub(TokenRevocationProofPort::class);
    $native->method('prove')->willReturnCallback(static function () use (&$current): TokenRevocationProof { return $current; });
    $policy = $this->policy($native);
    $command = new RevokeTokenCommand('signed-token', 'access_token');
    $before = $policy->authorize($command, null);
    $current = new TokenRevocationProof('access_token', 'token', 'client', 'user', 2, 4, true);
    $stored = new IdempotencyProof([...$before->receiptProof, ...$policy->proofForResult($command, new RevokeTokenResult(true))], new DateTimeImmutable('2030-01-01'));
    $after = $policy->authorize($command, $stored);

    self::assertSame($before->principalId, $after->principalId);
    self::assertSame($before->canonicalInput, $after->canonicalInput);
    self::assertSame('1', $after->receiptProof['revocation_after_revoked']);
    $replayed = $policy->replayResult(new RevokeTokenResult(true), $stored);
    self::assertInstanceOf(RevokeTokenResult::class, $replayed);
    self::assertTrue($replayed->revoked);
  }

  #[Test]
  public function changedClientEpochDeniesBeforeTheSavedRevocationResultIsOpened(): void
  {
    $native = $this->createStub(TokenRevocationProofPort::class);
    $native->method('prove')->willReturn(new TokenRevocationProof('access_token', 'token', 'client', null, 3, null, true));
    $policy = $this->policy($native);
    $this->expectException(IdempotencyDeniedException::class);

    $policy->authorize(new RevokeTokenCommand('signed-token'), new IdempotencyProof([
      'revocation_kind' => 'access_token', 'revocation_token_id' => 'token', 'revocation_client_id' => 'client', 'revocation_user_id' => '',
      'revocation_client_revision' => '2', 'revocation_user_revision' => 'absent', 'revocation_after_revoked' => '1',
    ], new DateTimeImmutable('2030-01-01')));
  }

  #[Test]
  public function unknownNativeTokenRetainsAnEmptyPublicOutcomeAndNoInventedPrincipal(): void
  {
    $native = $this->createStub(TokenRevocationProofPort::class);
    $native->method('prove')->willReturn(new TokenRevocationProof('unknown'));
    $policy = $this->policy($native);
    $first = $policy->authorize(new RevokeTokenCommand('unknown'), null);
    $proof = new IdempotencyProof($first->receiptProof, new DateTimeImmutable('2030-01-01'));

    self::assertSame($first->principalId, $policy->authorize(new RevokeTokenCommand('unknown'), $proof)->principalId);
    self::assertSame('', $first->receiptProof['revocation_user_id']);
    self::assertSame('', $first->receiptProof['revocation_client_id']);
    $replayed = $policy->replayResult(new RevokeTokenResult(false), $proof);
    self::assertInstanceOf(RevokeTokenResult::class, $replayed);
    self::assertFalse($replayed->revoked);
  }

  #[Test]
  public function aMissingFreshCallerDeniesBeforeAnyNativeTokenLookup(): void
  {
    $native = $this->createMock(TokenRevocationProofPort::class);
    $native->expects(self::never())->method('prove');
    $principals = $this->createStub(HttpIdempotencyPrincipalPort::class);
    $principals->method('current')->willReturn(null);
    $authorizer = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->expects(self::never())->method('authorize');
    $policy = new TokenRevocationIdempotencyProofAdapter($native, new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32))), $principals, $authorizer);
    $this->expectException(IdempotencyDeniedException::class);

    $policy->authorize(new RevokeTokenCommand('signed-token'), null);
  }

  private function policy(TokenRevocationProofPort $native): TokenRevocationIdempotencyProofAdapter
  {
    $principals = $this->createStub(HttpIdempotencyPrincipalPort::class);
    $principals->method('current')->willReturn(new HttpIdempotencyPrincipal('current-actor'));
    $authorizer = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $authorizer->method('authorize')->willReturnCallback(static function (HttpIdempotencyAuthorizationRequest $request): array {
      self::assertSame('current-actor', $request->principalId);
      self::assertSame('/api/oauth2/token/revoke', $request->resourceUri);
      self::assertSame("is_granted('ROLE_USER')", $request->platformSecurityExpression);

      return ['actor' => 'current-actor'];
    });

    return new TokenRevocationIdempotencyProofAdapter($native, new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('h', 32))), $principals, $authorizer);
  }
}
