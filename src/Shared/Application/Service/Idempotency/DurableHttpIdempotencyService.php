<?php

declare(strict_types=1);

namespace Shared\Application\Service\Idempotency;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use Shared\Application\Contract\Crypto\CiphertextRejectedException;
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyCompletion, IdempotencyDeniedException, IdempotencyLease, IdempotencyProof, IdempotencyReceipt, IdempotencyScope, IdempotencySnapshot};
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Crypto\AuthenticatedEncryptionPort;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPort, IdempotencyFingerprintPort, IdempotencyReceiptPort};

use function bin2hex;
use function hash_equals;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function strlen;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Class DurableHttpIdempotencyService
 *
 * Persists application outcomes within their owning write transaction.
 *
 * @category Service
 */
final readonly class DurableHttpIdempotencyService implements HttpIdempotencyPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param IdempotencyReceiptPort $receipts explicitly auth- or main-owned persistence
   * @param IdempotencyFingerprintPort $fingerprints dedicated keyed canonical fingerprints
   * @param AuthenticatedEncryptionPort $encryption dedicated versioned replay-secret keyring
   * @param ClockPort $clock UTC-capable wall clock
   * @param int $leaseSeconds pending reservation duration, seconds
   *
   * @return void
   */
  public function __construct(
    private IdempotencyReceiptPort $receipts,
    private IdempotencyFingerprintPort $fingerprints,
    private AuthenticatedEncryptionPort $encryption,
    private ClockPort $clock,
    private int $leaseSeconds = 60,
  ) {
    if ($leaseSeconds < 1 || $leaseSeconds > 3600) {
      throw new InvalidArgumentException('The idempotency lease duration is invalid.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function begin(IdempotencyScope $scope, string $key, mixed $canonicalInput, ?callable $authorizeReplay = null): IdempotencyBegin
  {
    if ('' === trim($key) || strlen($key) > 255) {
      throw new InvalidArgumentException('The idempotency key is invalid.');
    }
    $scopeHash = $this->scopeHash($scope);
    $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    $lease = new IdempotencyLease(
      $scopeHash,
      $this->fingerprints->digest('key', [$scopeHash, $key]),
      $this->fingerprints->digest('input', [$scopeHash, $canonicalInput]),
      bin2hex(random_bytes(32)),
      $now->modify('+' . $this->leaseSeconds . ' seconds'),
      $this->fingerprints->digest('principal', $scope->principalId),
      null === $scope->organizationId ? null : $this->fingerprints->digest('organization', $scope->organizationId),
    );
    $receipt = $this->receipts->reserve($lease, $now);
    if (!hash_equals($lease->fingerprint, $receipt->fingerprint)) {
      throw new IdempotencyDeniedException('input_mismatch');
    }
    if ('pending' === $receipt->state && hash_equals($lease->ownerToken, $receipt->ownerToken)) {
      return new IdempotencyBegin(lease: $lease);
    }
    if ('completed' === $receipt->state) {
      return new IdempotencyBegin(replay: $this->replay($lease, $receipt, $authorizeReplay));
    }

    throw new IdempotencyDeniedException('outcome_unknown' === $receipt->state ? 'commit_unknown' : 'in_progress');
  }

  /**
   * {@inheritdoc}
   */
  public function inspectProof(IdempotencyScope $scope, string $key): ?IdempotencyProof
  {
    if ('' === trim($key) || strlen($key) > 255) {
      throw new InvalidArgumentException('The idempotency key is invalid.');
    }
    $scopeHash = $this->scopeHash($scope);
    $lookup = new IdempotencyLease($scopeHash, $this->fingerprints->digest('key', [$scopeHash, $key]), '', '', $this->clock->now());
    $receipt = $this->receipts->find($lookup);
    if (null === $receipt || 'completed' !== $receipt->state) {
      return null;
    }
    $this->verifyProof($lookup, $receipt);

    // Tombstone authority remains inspectable after result erasure; the current gate and canonical input still precede expiry confirmation.
    return new IdempotencyProof($receipt->proof, $receipt->resultExpiresAt ?? throw new IdempotencyDeniedException('result_unavailable'));
  }

  /**
   * {@inheritdoc}
   */
  public function claimWithinTransaction(IdempotencyLease $lease, ?string $verifiedOrganizationId = null): void
  {
    if (null !== $verifiedOrganizationId && '' === trim($verifiedOrganizationId)) {
      throw new InvalidArgumentException('Claim requires a verified organization identifier.');
    }
    $organizationHash = null === $verifiedOrganizationId ? $lease->organizationHash : $this->fingerprints->digest('organization', $verifiedOrganizationId);
    if (null !== $lease->organizationHash && null !== $organizationHash && !hash_equals($lease->organizationHash, $organizationHash)) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    $this->receipts->claim($lease, $this->clock->now(), $organizationHash);
  }

  /**
   * {@inheritdoc}
   */
  public function beforeCommit(IdempotencyLease $lease, IdempotencySnapshot $snapshot): void
  {
    $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    $expiresAt = $now->modify($snapshot->secret ? '+5 minutes' : '+24 hours');
    $proofMac = $this->proofMac($lease, $lease->fingerprint, $expiresAt, $snapshot->proof);
    $payload = json_encode(['v' => 1, 'status' => $snapshot->statusCode, 'body' => $snapshot->body, 'headers' => $snapshot->headers], JSON_THROW_ON_ERROR);
    if ($snapshot->secret) {
      $payload = $this->encryption->encrypt($payload, $this->authenticatedData($lease, $expiresAt, $proofMac));
    }
    $organizationHash = isset($snapshot->proof['organizationId']) ? $this->fingerprints->digest('organization', $snapshot->proof['organizationId']) : $lease->organizationHash;
    if (null !== $lease->organizationHash && null !== $organizationHash && !hash_equals($lease->organizationHash, $organizationHash)) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    $this->receipts->complete($lease, new IdempotencyCompletion($payload, $snapshot->secret, $now, $expiresAt, $snapshot->proof, $proofMac, $organizationHash));
  }

  /**
   * {@inheritdoc}
   */
  public function confirmedRollback(IdempotencyLease $lease): void
  {
    $this->receipts->releaseAfterRollback($lease);
  }

  /**
   * {@inheritdoc}
   */
  public function commitUnknown(IdempotencyLease $lease): void
  {
    $this->receipts->markUnknown($lease);
  }

  /**
   * {@inheritdoc}
   */
  public function reconcile(IdempotencyLease $lease, ?callable $authorizeReplay = null): ?IdempotencySnapshot
  {
    $receipt = $this->receipts->find($lease);
    if (null === $receipt || 'completed' !== $receipt->state) {
      return null;
    }
    if (!hash_equals($lease->fingerprint, $receipt->fingerprint) || !hash_equals($lease->ownerToken, $receipt->ownerToken)) {
      throw new IdempotencyDeniedException('owner_lost');
    }

    return $this->replay($lease, $receipt, $authorizeReplay);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeScope(IdempotencyScope $scope): void
  {
    $this->receipts->purgeScope($this->scopeHash($scope));
  }

  /**
   * {@inheritdoc}
   */
  public function purgePrincipal(string $principalId): void
  {
    if ('' === trim($principalId)) {
      throw new InvalidArgumentException('A principal purge requires a verified identifier.');
    }
    $this->receipts->purgePrincipal($this->fingerprints->digest('principal', $principalId), $principalId);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeOrganization(string $organizationId): void
  {
    if ('' === trim($organizationId)) {
      throw new InvalidArgumentException('An organization purge requires a verified identifier.');
    }
    $this->receipts->purgeOrganization($this->fingerprints->digest('organization', $organizationId));
  }

  /**
   * Method scopeHash
   *
   * @access private
   *
   * @param IdempotencyScope $scope verified current operation scope
   *
   * @return string purpose-separated scope digest
   */
  private function scopeHash(IdempotencyScope $scope): string
  {
    return $this->fingerprints->digest('scope', [$scope->principalId, $scope->tenantId, $scope->organizationId, $scope->operation, $scope->resourceUri]);
  }

  /**
   * Method authenticatedData
   *
   * @access private
   *
   * @param IdempotencyLease $lease scoped operation identity
   * @param DateTimeImmutable $expiresAt absolute replay deadline
   * @param string $proofMac authenticated non-secret proof
   *
   * @return string authenticated receipt binding
   */
  private function authenticatedData(IdempotencyLease $lease, DateTimeImmutable $expiresAt, string $proofMac): string
  {
    return 'fireguard:http-result:v1:' . $lease->scopeHash . ':' . $lease->keyHash . ':' . $lease->fingerprint . ':' . $expiresAt->format('U.u') . ':' . $proofMac;
  }

  /**
   * Method proofMac
   *
   * @access private
   *
   * @param IdempotencyLease $lease scoped receipt identity
   * @param string $fingerprint original canonical request digest
   * @param DateTimeImmutable $expiresAt absolute replay deadline
   * @param array<string, string> $proof verified non-secret metadata
   *
   * @return string independently keyed proof authentication
   */
  private function proofMac(IdempotencyLease $lease, string $fingerprint, DateTimeImmutable $expiresAt, array $proof): string
  {
    return $this->fingerprints->digest('proof', [$lease->scopeHash, $lease->keyHash, $fingerprint, $expiresAt->format('U.u'), $proof]);
  }

  /**
   * Method verifyProof
   *
   * @access private
   *
   * @param IdempotencyLease $lease current scoped lookup
   * @param IdempotencyReceipt $receipt completed durable receipt
   *
   * @return void
   */
  private function verifyProof(IdempotencyLease $lease, IdempotencyReceipt $receipt): void
  {
    if (null === $receipt->resultExpiresAt || null === $receipt->proofMac || !hash_equals($receipt->proofMac, $this->proofMac($lease, $receipt->fingerprint, $receipt->resultExpiresAt, $receipt->proof))) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }

  /**
   * Method stringMetadata
   *
   * @access private
   *
   * @param array<mixed, mixed> $values decoded result metadata
   *
   * @return array<string, string> validated scalar metadata
   */
  private function stringMetadata(array $values): array
  {
    $validated = [];
    foreach ($values as $name => $value) {
      if (!is_string($name) || !is_string($value)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $validated[$name] = $value;
    }

    return $validated;
  }

  /**
   * Method replay
   *
   * @access private
   *
   * @param IdempotencyLease $lease replay identity
   * @param IdempotencyReceipt $receipt durable completed receipt
   * @param (callable(IdempotencyProof):void)|null $authorizeReplay current gate for this authenticated selected receipt
   *
   * @return IdempotencySnapshot original application outcome
   */
  private function replay(IdempotencyLease $lease, IdempotencyReceipt $receipt, ?callable $authorizeReplay = null): IdempotencySnapshot
  {
    $this->verifyProof($lease, $receipt);
    if (null !== $authorizeReplay) {
      $authorizeReplay(new IdempotencyProof($receipt->proof, $receipt->resultExpiresAt ?? throw new IdempotencyDeniedException('result_unavailable')));
    }
    if (null === $receipt->resultExpiresAt || $receipt->resultExpiresAt <= $this->clock->now()) {
      throw new IdempotencyDeniedException('expired', operationCompleted: true);
    }
    if (null === $receipt->payload) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    try {
      $payload = $receipt->secret ? $this->encryption->decrypt($receipt->payload, $this->authenticatedData($lease, $receipt->resultExpiresAt, $receipt->proofMac ?? '')) : $receipt->payload;
      $decoded = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
      if (!is_array($decoded) || 1 !== ($decoded['v'] ?? null) || !is_int($decoded['status'] ?? null) || !is_string($decoded['body'] ?? null) || !is_array($decoded['headers'] ?? null)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }

      return new IdempotencySnapshot($decoded['status'], $decoded['body'], $this->stringMetadata($decoded['headers']), $receipt->secret, $receipt->proof);
    } catch (JsonException|CiphertextRejectedException|InvalidArgumentException) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
  }
  // #endregion
}
