<?php

declare(strict_types=1);

namespace Tests\Integration\Otp\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use Otp\Infrastructure\Persistence\Doctrine\Mapper\OtpMapper;
use Otp\Infrastructure\Persistence\Doctrine\Repository\OtpRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test OtpRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OtpRepository::class)]
final class OtpRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private OtpRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new OtpRepository(
      entityManager: $this->entityManager,
      mapper: new OtpMapper(),
    );
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testSaveAndFindByIdAndChallengeToken(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-1',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'user@example.com',
    );
    $otp->releaseEvents();

    $this->repository->save($otp);

    $foundById = $this->repository->findById(new OtpId('123e4567-e89b-12d3-a456-426614174000'));
    $foundByToken = $this->repository->findByChallengeToken($otp->challengeToken());

    self::assertNotNull($foundById);
    self::assertNotNull($foundByToken);
    self::assertSame('user-1', $foundById->userId());
    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $foundByToken->id()->value);
  }

  #[Test]
  public function testFindActiveByUserAndPurposeReturnsActiveOtp(): void
  {
    $activeOtp = $this->createOtp(
      id: '123e4567-e89b-12d3-a456-426614174010',
      userId: 'user-2',
      purpose: OtpPurpose::LOGIN,
      expiresAt: new DateTimeImmutable('+10 minutes'),
      createdAt: new DateTimeImmutable('-5 minutes'),
    );
    $expiredOtp = $this->createOtp(
      id: '123e4567-e89b-12d3-a456-426614174011',
      userId: 'user-2',
      purpose: OtpPurpose::LOGIN,
      expiresAt: new DateTimeImmutable('-5 minutes'),
      createdAt: new DateTimeImmutable('-10 minutes'),
    );

    $this->repository->save($activeOtp);
    $this->repository->save($expiredOtp);

    $found = $this->repository->findActiveByUserAndPurpose('user-2', OtpPurpose::LOGIN);

    self::assertNotNull($found);
    self::assertSame($activeOtp->id()->value, $found->id()->value);
  }

  #[Test]
  public function testRevokeAllForUserExpiresActiveOtps(): void
  {
    $otpOne = $this->createOtp(
      id: '123e4567-e89b-12d3-a456-426614174020',
      userId: 'user-3',
      purpose: OtpPurpose::LOGIN,
      expiresAt: new DateTimeImmutable('+15 minutes'),
      createdAt: new DateTimeImmutable('-2 minutes'),
    );
    $otpTwo = $this->createOtp(
      id: '123e4567-e89b-12d3-a456-426614174021',
      userId: 'user-3',
      purpose: OtpPurpose::LOGIN,
      expiresAt: new DateTimeImmutable('+20 minutes'),
      createdAt: new DateTimeImmutable('-1 minutes'),
    );
    $expiredOtp = $this->createOtp(
      id: '123e4567-e89b-12d3-a456-426614174022',
      userId: 'user-3',
      purpose: OtpPurpose::LOGIN,
      expiresAt: new DateTimeImmutable('-1 minutes'),
      createdAt: new DateTimeImmutable('-30 minutes'),
    );

    $this->repository->save($otpOne);
    $this->repository->save($otpTwo);
    $this->repository->save($expiredOtp);

    $count = $this->repository->revokeAllForUser('user-3', OtpPurpose::LOGIN);

    self::assertSame(2, $count);
    self::assertNull($this->repository->findActiveByUserAndPurpose('user-3', OtpPurpose::LOGIN));
  }

  #[Test]
  public function testFindByChallengeTokenReturnsNullWhenMissing(): void
  {
    $missing = $this->repository->findByChallengeToken(ChallengeToken::generate());

    self::assertNull($missing);
  }
  // #endregion

  // #region Helpers
  private function createOtp(
    string $id,
    string $userId,
    OtpPurpose $purpose,
    DateTimeImmutable $expiresAt,
    ?DateTimeImmutable $createdAt = null,
  ): Otp {
    $code = OtpCode::generate();

    return Otp::reconstitute(
      id: new OtpId($id),
      challengeToken: ChallengeToken::generate(),
      userId: $userId,
      purpose: $purpose,
      channel: OtpChannel::EMAIL,
      codeHash: $code->hash(),
      recipient: 'user@example.com',
      expiresAt: $expiresAt,
      maxAttempts: 5,
      attempts: 0,
      verifiedAt: null,
      createdAt: $createdAt ?? new DateTimeImmutable(),
    );
  }
  // #endregion
}
