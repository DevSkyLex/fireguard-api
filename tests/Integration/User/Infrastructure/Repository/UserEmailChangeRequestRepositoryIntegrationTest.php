<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\Email;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Domain\Model\EmailChange\EmailChangeRequest;
use User\Domain\ValueObject\UserId;
use User\Infrastructure\Persistence\Doctrine\Record\UserEmailChangeRequestRecord;
use User\Infrastructure\Persistence\Doctrine\Repository\UserEmailChangeRequestRepository;

use function hash;

/**
 * Test UserEmailChangeRequestRepositoryIntegrationTest.
 *
 * The atomic single-use guard on the email change confirmation: the
 * conditional `UPDATE … WHERE confirmed_at IS NULL AND expires_at > now`
 * must consume a pending request exactly once — the second identical
 * call, and any call on an expired request, must report zero rows.
 * Exercised against the real auth PostgreSQL test database, because
 * the WHERE clause of the UPDATE IS the concurrency contract.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserEmailChangeRequestRepository::class)]
final class UserEmailChangeRequestRepositoryIntegrationTest extends KernelTestCase
{
  private const string REQUEST_ID = '9b0e8400-e29b-41d4-a716-446655440e01';

  private const string USER_ID = '9b0e8400-e29b-41d4-a716-446655440e02';

  // #region Properties
  private EntityManagerInterface $entityManager;

  private UserEmailChangeRequestRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new UserEmailChangeRequestRepository($this->entityManager);

    $existing = $this->entityManager->find(UserEmailChangeRequestRecord::class, self::REQUEST_ID);
    if ($existing instanceof UserEmailChangeRequestRecord) {
      $this->entityManager->remove($existing);
      $this->entityManager->flush();
    }
  }

  protected function tearDown(): void
  {
    $existing = $this->entityManager->find(UserEmailChangeRequestRecord::class, self::REQUEST_ID);
    if ($existing instanceof UserEmailChangeRequestRecord) {
      $this->entityManager->remove($existing);
      $this->entityManager->flush();
    }

    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testConfirmIfPendingConsumesAPendingRequestExactlyOnce(): void
  {
    $now = new DateTimeImmutable('2026-08-28 10:00:00');
    $this->repository->save($this->makeRequest(requestedAt: $now->modify('-10 minutes')));

    // First call wins: one row updated.
    self::assertTrue($this->repository->confirmIfPending(self::REQUEST_ID, $now));

    // Second call — the concurrent loser — sees zero pending rows.
    self::assertFalse($this->repository->confirmIfPending(self::REQUEST_ID, $now));

    // The consumed state is durable and visible to the active lookups.
    $this->entityManager->clear();
    $record = $this->entityManager->find(UserEmailChangeRequestRecord::class, self::REQUEST_ID);
    self::assertInstanceOf(UserEmailChangeRequestRecord::class, $record);
    self::assertNotNull($record->confirmedAt);
    self::assertNull($this->repository->findActiveByTokenHash($this->tokenHash(), $now));
  }

  #[Test]
  public function testConfirmIfPendingRefusesAnExpiredRequest(): void
  {
    $requestedAt = new DateTimeImmutable('2026-08-28 08:00:00');
    $this->repository->save($this->makeRequest(requestedAt: $requestedAt));

    // Two hours later the 1 h TTL is long gone: zero rows, no consume.
    $now = $requestedAt->modify('+2 hours');
    self::assertFalse($this->repository->confirmIfPending(self::REQUEST_ID, $now));

    $this->entityManager->clear();
    $record = $this->entityManager->find(UserEmailChangeRequestRecord::class, self::REQUEST_ID);
    self::assertInstanceOf(UserEmailChangeRequestRecord::class, $record);
    self::assertNull($record->confirmedAt);
  }

  #[Test]
  public function testConfirmIfPendingRefusesAnUnknownRequest(): void
  {
    self::assertFalse($this->repository->confirmIfPending(
      '9b0e8400-e29b-41d4-a716-446655440eff',
      new DateTimeImmutable('2026-08-28 10:00:00'),
    ));
  }
  // #endregion

  // #region Helpers
  private function makeRequest(DateTimeImmutable $requestedAt): EmailChangeRequest
  {
    return EmailChangeRequest::request(
      id: self::REQUEST_ID,
      userId: new UserId(self::USER_ID),
      currentEmail: new Email('email-change-it-current@example.com'),
      newEmail: new Email('email-change-it-new@example.com'),
      tokenHash: $this->tokenHash(),
      requestedAt: $requestedAt,
    );
  }

  private function tokenHash(): string
  {
    return hash('sha256', 'integration-test-email-change-token');
  }
  // #endregion
}
