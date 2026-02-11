<?php

declare(strict_types=1);

namespace Tests\Integration\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Consent\Consent;
use OAuth\Domain\ValueObject\Consent\ConsentId;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\ConsentRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test ConsentRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ConsentRepository::class)]
final class ConsentRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private ConsentRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new ConsentRepository(entityManager: $this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testSaveAndFindById(): void
  {
    $consent = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174000',
      userId: 'user-1',
      clientId: 'client-1',
      scopes: ['OPENID'],
    );

    $this->repository->save($consent);

    $found = $this->repository->findById(new ConsentId('123e4567-e89b-12d3-a456-426614174000'));

    self::assertNotNull($found);
    self::assertSame('user-1', $found->userId());
    self::assertSame('client-1', $found->clientId());
    self::assertSame(['OPENID'], $found->scopes()->toArray());
  }

  #[Test]
  public function testFindByUserAndClientReturnsActiveConsent(): void
  {
    $consent = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174010',
      userId: 'user-2',
      clientId: 'client-2',
      scopes: ['OPENID', 'EMAIL'],
    );

    $this->repository->save($consent);

    $found = $this->repository->findByUserAndClient('user-2', 'client-2');

    self::assertNotNull($found);
    self::assertSame('123e4567-e89b-12d3-a456-426614174010', $found->id()->value);
  }

  #[Test]
  public function testSaveUpdatesScopes(): void
  {
    $consent = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174020',
      userId: 'user-3',
      clientId: 'client-3',
      scopes: ['OPENID'],
    );

    $this->repository->save($consent);

    $consent->updateScopes(Scopes::fromArray(['OPENID', 'EMAIL']));
    $this->repository->save($consent);

    $found = $this->repository->findById(new ConsentId('123e4567-e89b-12d3-a456-426614174020'));

    self::assertNotNull($found);
    self::assertSame(['OPENID', 'EMAIL'], $found->scopes()->toArray());
  }

  #[Test]
  public function testFindAllByUserReturnsAllConsents(): void
  {
    $consentOne = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174030',
      userId: 'user-4',
      clientId: 'client-4',
      scopes: ['OPENID'],
    );
    $consentTwo = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174031',
      userId: 'user-4',
      clientId: 'client-5',
      scopes: ['PROFILE'],
    );
    $otherUserConsent = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174032',
      userId: 'user-5',
      clientId: 'client-6',
      scopes: ['EMAIL'],
    );

    $this->repository->save($consentOne);
    $this->repository->save($consentTwo);
    $this->repository->save($otherUserConsent);

    $all = $this->repository->findAllByUser('user-4');

    self::assertCount(2, $all);
  }

  #[Test]
  public function testRevokeAllForUserMarksConsentsRevoked(): void
  {
    $consentOne = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174040',
      userId: 'user-6',
      clientId: 'client-7',
      scopes: ['OPENID'],
    );
    $consentTwo = $this->createConsent(
      id: '123e4567-e89b-12d3-a456-426614174041',
      userId: 'user-6',
      clientId: 'client-8',
      scopes: ['EMAIL'],
    );

    $this->repository->save($consentOne);
    $this->repository->save($consentTwo);

    $count = $this->repository->revokeAllForUser('user-6');

    self::assertSame(2, $count);
    self::assertNull($this->repository->findByUserAndClient('user-6', 'client-7'));
    self::assertNull($this->repository->findByUserAndClient('user-6', 'client-8'));
  }
  // #endregion

  // #region Helpers
  /**
   * @param array<string> $scopes
   */
  private function createConsent(string $id, string $userId, string $clientId, array $scopes): Consent
  {
    return Consent::grant(
      id: new ConsentId($id),
      userId: $userId,
      clientId: $clientId,
      scopes: Scopes::fromArray($scopes),
    );
  }
  // #endregion
}
