<?php

declare(strict_types=1);

namespace Tests\Integration\Client\Infrastructure\Repository;

use Doctrine\ORM\EntityManagerInterface;
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use OAuth\Infrastructure\Persistence\Doctrine\Repository\Client\ClientRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_intersect;
use function array_map;
use function intdiv;
use function password_hash;
use function sprintf;

use const PASSWORD_BCRYPT;

/**
 * Test ClientRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ClientRepository::class)]
final class ClientRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private ClientRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new ClientRepository($this->entityManager);
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
    $client = $this->createTestClient('123e4567-e89b-12d3-a456-426614174001', 'Test Client 1');

    $this->repository->save($client);

    $foundClient = $this->repository->findById(new ClientId('123e4567-e89b-12d3-a456-426614174001'));

    self::assertNotNull($foundClient);
    self::assertSame('123e4567-e89b-12d3-a456-426614174001', $foundClient->id()->value);
    self::assertSame('Test Client 1', $foundClient->name()->value);
    self::assertTrue($foundClient->isActive());
  }

  #[Test]
  public function testFindByIdReturnsNullWhenNotFound(): void
  {
    $foundClient = $this->repository->findById(new ClientId('00000000-0000-4000-8000-000000000000'));

    self::assertNull($foundClient);
  }

  #[Test]
  public function testSaveUpdatesExistingClient(): void
  {
    $client = $this->createTestClient('123e4567-e89b-12d3-a456-426614174002', 'Original Name');
    $this->repository->save($client);

    // Retrieve and modify
    $foundClient = $this->repository->findById(new ClientId('123e4567-e89b-12d3-a456-426614174002'));
    self::assertNotNull($foundClient);

    // Deactivate the client
    $eventIdProvider = new UuidEventIdProvider(new UuidGeneratorAdapter());
    $foundClient->deactivate($eventIdProvider);
    $this->repository->save($foundClient);

    // Verify update
    $updatedClient = $this->repository->findById(new ClientId('123e4567-e89b-12d3-a456-426614174002'));
    self::assertNotNull($updatedClient);
    self::assertFalse($updatedClient->isActive());
  }

  #[Test]
  public function testExistsByName(): void
  {
    $client = $this->createTestClient('123e4567-e89b-12d3-a456-426614174003', 'Unique Client Name');
    $this->repository->save($client);

    self::assertTrue($this->repository->existsByName(new ClientName('Unique Client Name')));
    self::assertFalse($this->repository->existsByName(new ClientName('Non Existent Name')));
  }

  #[Test]
  public function testDelete(): void
  {
    $client = $this->createTestClient('123e4567-e89b-12d3-a456-426614174004', 'Client To Delete');
    $this->repository->save($client);

    // Verify exists
    $foundClient = $this->repository->findById(new ClientId('123e4567-e89b-12d3-a456-426614174004'));
    self::assertNotNull($foundClient);

    // Delete
    $this->repository->delete($foundClient);

    // Verify deleted
    $deletedClient = $this->repository->findById(new ClientId('123e4567-e89b-12d3-a456-426614174004'));
    self::assertNull($deletedClient);
  }

  #[Test]
  public function testFindAllWithPagination(): void
  {
    // Create multiple clients
    for ($i = 1; $i <= 5; ++$i) {
      $client = $this->createTestClient(
        sprintf('123e4567-e89b-12d3-a456-42661417400%d', $i),
        "Client $i",
      );
      $this->repository->save($client);
    }

    // Asserted against the total rather than a fixed 5: the test databases
    // carry a seeded baseline this test does not own.
    $total = $this->repository->count();

    $firstPage = $this->repository->findAll(0, 2);
    $secondPage = $this->repository->findAll(2, 2);

    self::assertCount(2, $firstPage);
    self::assertCount(2, $secondPage);

    // The clients above are all created within the same second, so this only
    // holds because findAll() breaks ties on `id`. Without that tiebreaker the
    // rows sort arbitrarily and LIMIT/OFFSET repeats some across pages.
    $idOf = static fn (Client $client): string => $client->id()->value;
    self::assertSame(
      [],
      array_intersect(array_map($idOf, $firstPage), array_map($idOf, $secondPage)),
      'Consecutive pages must not return the same client twice.',
    );

    $lastOffset = intdiv($total - 1, 2) * 2;
    self::assertCount($total - $lastOffset, $this->repository->findAll($lastOffset, 2));
    self::assertCount(0, $this->repository->findAll($total, 2));
  }

  #[Test]
  public function testCount(): void
  {
    // A delta, not an absolute: the seeded baseline already holds clients.
    $before = $this->repository->count();

    for ($i = 1; $i <= 3; ++$i) {
      $client = $this->createTestClient(
        sprintf('123e4567-e89b-12d3-a456-52661417400%d', $i),
        "Count Client $i",
      );
      $this->repository->save($client);
    }

    self::assertSame($before + 3, $this->repository->count());
  }

  #[Test]
  public function testClientWithMultipleRedirectUris(): void
  {
    $eventIdProvider = new UuidEventIdProvider(new UuidGeneratorAdapter());
    $client = Client::register(
      id: new ClientId('123e4567-e89b-12d3-a456-426614174010'),
      name: new ClientName('Multi Redirect Client'),
      secret: new ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [
        new RedirectUri('https://example.com/callback1'),
        new RedirectUri('https://example.com/callback2'),
        new RedirectUri('https://example.com/callback3'),
      ],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE, GrantType::REFRESH_TOKEN),
      scopes: new Scopes(Scope::READ, Scope::WRITE, Scope::OPENID),
      eventIdProvider: $eventIdProvider,
    );
    $client->releaseEvents();

    $this->repository->save($client);

    $foundClient = $this->repository->findById(new ClientId('123e4567-e89b-12d3-a456-426614174010'));
    self::assertNotNull($foundClient);
    self::assertCount(3, $foundClient->redirectUris());
    self::assertCount(2, $foundClient->grantTypes()->toArray());
    self::assertCount(3, $foundClient->scopes()->toArray());
  }
  // #endregion

  // #region Helpers
  private function createTestClient(string $id, string $name): Client
  {
    $eventIdProvider = new UuidEventIdProvider(new UuidGeneratorAdapter());
    $client = Client::register(
      id: new ClientId($id),
      name: new ClientName($name),
      secret: new ClientSecret(password_hash('test-secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://example.com/callback')],
      grantTypes: new GrantTypes(GrantType::CLIENT_CREDENTIALS),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: $eventIdProvider,
    );
    $client->releaseEvents();

    return $client;
  }
  // #endregion
}
