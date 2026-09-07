<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Durable organization setup recovery.
 *
 * @category FunctionalTest
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OnboardingSetupApiTest extends WebTestCase
{
  private const string USER = '550e8400-e29b-41d4-a716-446655446901';

  private const string OTHER = '550e8400-e29b-41d4-a716-446655446902';

  private string $token = '';

  #[Test]
  public function preparationRequiresAuthentication(): void
  {
    $client = static::createClient();
    $client->request('POST', '/api/onboarding/organization/setup-operations', server: ['CONTENT_TYPE' => 'application/ld+json'], content: '{}');
    self::assertResponseStatusCodeSame(401);
  }

  #[Test]
  public function foreignSessionAndMissingPreparationAreRejected(): void
  {
    $client = $this->client();
    $session = $this->start($client);
    $this->login(self::OTHER);
    $this->call($client, 'POST', '/api/onboarding/organization/setup-operations', ['sessionId' => $session, 'stepKey' => 'create_organization', 'items' => [['itemKey' => 'org', 'payload' => ['name' => 'ACME']]]]);
    self::assertResponseStatusCodeSame(409);
    $this->login(self::USER);
    $this->call($client, 'POST', '/api/organizations', ['name' => 'ACME', 'onboardingSessionId' => $session, 'onboardingItemKey' => 'unprepared']);
    self::assertResponseStatusCodeSame(409);
    self::assertSame(0, $this->countCreatedOrganizations());
  }

  #[Test]
  public function committedCreationAndConfirmationCanBeReplayedAfterReloadWithoutDuplicates(): void
  {
    $client = $this->client();
    $session = $this->start($client);
    $payload = ['name' => 'Recovery Company'];
    $this->prepare($client, $session, 'create_organization', [['itemKey' => 'org', 'payload' => $payload]]);
    $body = [...$payload, 'onboardingSessionId' => $session, 'onboardingItemKey' => 'org'];
    $this->call($client, 'POST', '/api/organizations', $body);
    self::assertResponseStatusCodeSame(201);
    $created = $this->body($client)['id'];
    self::assertIsString($created);
    // A new GET simulates returning after the resource response/confirmation was lost.
    $this->call($client, 'GET', '/api/onboarding/organization');
    self::assertResponseStatusCodeSame(200);
    $state = $this->body($client);
    /** @var list<array{resourceId:?string,status:string}> $setup */
    $setup = $state['setupOperations'];
    self::assertSame($created, $setup[0]['resourceId']);
    self::assertSame('completed', $setup[0]['status']);
    self::assertSame('create_organization', $state['nextStep']);
    $this->call($client, 'POST', '/api/organizations', $body);
    self::assertResponseStatusCodeSame(201);
    self::assertSame($created, $this->body($client)['id']);
    self::assertSame(1, $this->countCreatedOrganizations());
    $this->call($client, 'POST', '/api/onboarding/organization/steps/create_organization/execute');
    self::assertResponseStatusCodeSame(200);
    $confirmed = $this->body($client);
    $this->call($client, 'POST', '/api/onboarding/organization/steps/create_organization/execute');
    self::assertResponseStatusCodeSame(200);
    self::assertSame('select_plan', $this->body($client)['nextStep']);
    /** @var list<array{stepKey:string,occurredAt:string,skipped:bool}> $before */
    $before = $confirmed['stepHistory'];
    /** @var list<array{stepKey:string,occurredAt:string,skipped:bool}> $after */
    $after = $this->body($client)['stepHistory'];
    self::assertCount(1, $after);
    self::assertSame($before[0]['stepKey'], $after[0]['stepKey']);
    self::assertSame($before[0]['occurredAt'], $after[0]['occurredAt']);
    self::assertSame($before[0]['skipped'], $after[0]['skipped']);
  }

  #[Test]
  public function aPartialFacilityBatchResumesOnlyRemainingItemsAndEquipmentAssignmentIsAtomic(): void
  {
    $client = $this->client();
    $session = $this->start($client);
    $this->prepare($client, $session, 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Partial Recovery']]]);
    $this->call($client, 'POST', '/api/organizations', ['name' => 'Partial Recovery', 'onboardingSessionId' => $session, 'onboardingItemKey' => 'org']);
    self::assertResponseStatusCodeSame(201);
    $org = $this->body($client)['id'];
    self::assertIsString($org);
    $this->call($client, 'POST', '/api/onboarding/organization/steps/create_organization/execute');
    self::assertResponseStatusCodeSame(200);
    $this->call($client, 'POST', '/api/onboarding/organization/steps/select_plan/skip');
    self::assertResponseStatusCodeSame(200);
    $this->call($client, 'POST', '/api/onboarding/organization/steps/invite_members/skip');
    self::assertResponseStatusCodeSame(200);
    $a = ['name' => 'First site', 'type' => 'site'];
    $b = ['name' => 'Second site', 'type' => 'site'];
    $this->prepare($client, $session, 'create_first_facility', [['itemKey' => 'a', 'payload' => $a], ['itemKey' => 'b', 'payload' => $b]]);
    $this->call($client, 'POST', '/api/organizations/' . $org . '/facilities', [...$a, 'onboardingSessionId' => $session, 'onboardingItemKey' => 'a']);
    self::assertResponseStatusCodeSame(201);
    $facility = $this->body($client)['id'];
    self::assertIsString($facility);
    $this->call($client, 'POST', '/api/onboarding/organization/steps/create_first_facility/execute');
    self::assertResponseStatusCodeSame(409);
    $this->call($client, 'GET', '/api/onboarding/organization');
    /** @var list<array{itemKey:string,resourceId:?string,status:string,payload:array{name:string}}> $operations */
    $operations = $this->body($client)['setupOperations'];
    self::assertSame('a', $operations[1]['itemKey']);
    self::assertSame($facility, $operations[1]['resourceId']);
    self::assertSame('prepared', $operations[2]['status']);
    self::assertSame('Second site', $operations[2]['payload']['name']);
    $this->call($client, 'POST', '/api/organizations/' . $org . '/facilities', [...$a, 'onboardingSessionId' => $session, 'onboardingItemKey' => 'a']);
    self::assertResponseStatusCodeSame(201);
    self::assertSame($facility, $this->body($client)['id']);
    $this->call($client, 'POST', '/api/organizations/' . $org . '/facilities', [...$b, 'onboardingSessionId' => $session, 'onboardingItemKey' => 'b']);
    self::assertResponseStatusCodeSame(201);
    $this->call($client, 'POST', '/api/onboarding/organization/steps/create_first_facility/execute');
    self::assertResponseStatusCodeSame(200);
    $equipment = ['type' => 'fire_extinguisher', 'facility' => '/api/facilities/' . $facility];
    $this->prepare($client, $session, 'create_first_equipment', [['itemKey' => 'equipment', 'payload' => $equipment]]);
    $this->call($client, 'POST', '/api/organizations/' . $org . '/equipment', [...$equipment, 'onboardingSessionId' => $session, 'onboardingItemKey' => 'equipment']);
    self::assertResponseStatusCodeSame(201);
    $created = $this->body($client);
    self::assertSame($facility, $created['facilityId']);
    $this->call($client, 'POST', '/api/organizations/' . $org . '/equipment', [...$equipment, 'onboardingSessionId' => $session, 'onboardingItemKey' => 'equipment']);
    self::assertResponseStatusCodeSame(201);
    self::assertSame($created['id'], $this->body($client)['id']);
    self::assertSame($created['installedAt'], $this->body($client)['installedAt']);
  }

  #[Test]
  public function creatorReceiptPinsAndRollsBackItsOrganizationInsteadOfANewerUnrelatedCreation(): void
  {
    $client = $this->client();
    $session = $this->start($client);
    $this->prepare($client, $session, 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Wizard Company']]]);
    $this->call($client, 'POST', '/api/organizations', ['name' => 'Wizard Company', 'onboardingSessionId' => $session, 'onboardingItemKey' => 'org']);
    self::assertResponseStatusCodeSame(201);
    $wizardId = $this->body($client)['id'];
    self::assertIsString($wizardId);
    $this->call($client, 'POST', '/api/organizations', ['name' => 'Independent Company']);
    self::assertResponseStatusCodeSame(201);
    $independentId = $this->body($client)['id'];
    self::assertIsString($independentId);
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    // Make ordering deterministic even when both writes share a timestamp.
    $manager->getConnection()->executeStatement("UPDATE organizations SET created_at = created_at + INTERVAL '1 hour' WHERE id = ?", [$independentId]);
    $manager->clear();
    $this->call($client, 'GET', '/api/onboarding/organization');
    self::assertResponseStatusCodeSame(200);
    self::assertSame($wizardId, $this->body($client)['targetOrganizationId']);
    $this->call($client, 'POST', '/api/onboarding/organization/steps/create_organization/execute');
    self::assertResponseStatusCodeSame(200);
    self::assertTrue($this->body($client)['canRollback']);
    $this->call($client, 'POST', '/api/onboarding/organization/rollback');
    self::assertResponseStatusCodeSame(200);
    self::assertSame('archived', $manager->getConnection()->fetchOne('SELECT status FROM organizations WHERE id = ?', [$wizardId]));
    self::assertSame('active', $manager->getConnection()->fetchOne('SELECT status FROM organizations WHERE id = ?', [$independentId]));
    $this->call($client, 'GET', '/api/onboarding/organization');
    self::assertResponseStatusCodeSame(200);
    self::assertNull($this->body($client)['targetOrganizationId']);
    self::assertFalse($this->body($client)['canRollback']);
    self::assertSame([], $this->body($client)['setupOperations']);
  }

  private function client(): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (UserId $id) => UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    static::getContainer()->set(UserRepositoryPort::class, $users);
    $this->login(self::USER);

    return $client;
  }

  /**
   * @param non-empty-string $userId authenticated fixture identifier
   */
  private function login(string $userId): void
  {
    self::assertNotSame('', $userId);
    $tokens = static::getContainer()->get(JwtTokenServicePort::class);
    self::assertInstanceOf(JwtTokenServicePort::class, $tokens);
    $this->token = $tokens->generateTokens($userId, $userId . '@corp.example')['access_token'];
  }

  private function start(KernelBrowser $client): string
  {
    $this->call($client, 'POST', '/api/onboarding/organization/start', ['intent' => 'create']);
    self::assertResponseStatusCodeSame(200);
    $id = $this->body($client)['sessionId'];
    self::assertIsString($id);

    return $id;
  }

  /**
   * @param list<array{itemKey:string,payload:array<string,mixed>}> $items
   */
  private function prepare(KernelBrowser $client, string $session, string $step, array $items): void
  {
    $this->call($client, 'POST', '/api/onboarding/organization/setup-operations', ['sessionId' => $session, 'stepKey' => $step, 'items' => $items]);
    self::assertResponseStatusCodeSame(200);
  }

  /**
   * @param array<string,mixed> $body
   */
  private function call(KernelBrowser $client, string $method, string $path, array $body = []): void
  {
    $client->request($method, $path, server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token], content: [] === $body ? '{}' : json_encode($body, JSON_THROW_ON_ERROR));
  }

  /**
   * @return array<string,mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($body);

    /** @var array<string,mixed> $body */
    return $body;
  }

  private function countCreatedOrganizations(): int
  {
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);

    $count = $em->getConnection()->fetchOne('SELECT COUNT(*) FROM organizations WHERE created_by_user_id = ?', [self::USER]);
    self::assertTrue(is_int($count) || is_string($count));

    return (int) $count;
  }
}
