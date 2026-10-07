<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use MaintenanceCost\Application\Port\Outbound\Rate\MaintenanceRateStorePort;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** Dedicated finance permissions, organization isolation and immutable rate creation are exercised over HTTP. */
final class MaintenanceCurrencyRatesApiTest extends WebTestCase
{
  private const string ORG = '650e8400-e29b-41d4-a716-448040000001';

  private const string OWNER = '650e8400-e29b-41d4-a716-448040000002';

  private const string USER = '650e8400-e29b-41d4-a716-448040000003';

  private const string MEMBER = '650e8400-e29b-41d4-a716-448040000004';

  private const string CLIENT = '650e8400-e29b-41d4-a716-448040000010';

  private const string RATE = '650e8400-e29b-41d4-a716-448040000011';

  #[Test]
  public function readsDefaultCurrencyWithoutWritingASetting(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'GET', '/currency');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('EUR', $body['currency']);
    self::assertFalse($body['locked']);
    self::assertSame(0, $this->countRows('maintenance_cost_currency_settings'));
  }

  #[Test]
  public function configuresCurrencyBeforeTheFirstFinancialFact(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'PATCH', '/currency', ['currency' => 'USD']);
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('USD', $body['currency']);
    self::assertFalse($body['locked']);
  }

  #[Test]
  public function aCapturedFactPreventsCurrencyChanges(): void
  {
    $client = $this->client();
    /** @var MaintenanceCurrencyPort $currency */
    $currency = self::getContainer()->get(MaintenanceCurrencyPort::class);
    $this->main()->getConnection()->transactional(static fn () => $currency->lock(self::ORG));
    $this->request($client, 'PATCH', '/currency', ['currency' => 'USD']);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('EUR', $currency->forOrganization(self::ORG));
  }

  #[Test]
  public function createsAnExactRateForAnOrganizationMember(): void
  {
    $client = $this->client();
    $body = $this->request($client, 'POST', '/rates', $this->rateInput());
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('42.000001', $body['hourlyAmount']);
    self::assertSame(self::MEMBER, $body['memberId']);
    self::assertSame('EUR', $body['currency']);
    self::assertSame('2026-01-01', $body['effectiveFrom']);
    self::assertFalse($body['replayed']);
  }

  #[Test]
  public function idempotentRequestKeepsItsOnlyRate(): void
  {
    $client = $this->client();
    $this->seedRate();
    $body = $this->request($client, 'POST', '/rates', $this->rateInput());
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(self::RATE, $body['id']);
    self::assertTrue($body['replayed']);
    self::assertSame(1, $this->countRows('maintenance_cost_hourly_rates'));
  }

  #[Test]
  public function differentParametersCannotReuseAClientIdentity(): void
  {
    $client = $this->client();
    $this->seedRate();
    $input = $this->rateInput();
    $input['hourlyAmount'] = '43';
    $this->request($client, 'POST', '/rates', $input);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function anotherRequestCannotOverwriteTheSameEffectiveDate(): void
  {
    $client = $this->client();
    $this->seedRate();
    $input = $this->rateInput();
    $input['clientId'] = '650e8400-e29b-41d4-a716-448040000012';
    $this->request($client, 'POST', '/rates', $input);
    self::assertSame(409, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function memberFromAnotherOrganizationIsNotFound(): void
  {
    $client = $this->client();
    $input = $this->rateInput();
    $input['memberId'] = '650e8400-e29b-41d4-a716-448040000099';
    $this->request($client, 'POST', '/rates', $input);
    self::assertSame(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
  }

  #[Test]
  public function listsExactRatesWithTheSameMemberFilterAndTotal(): void
  {
    $client = $this->client();
    $this->seedRate();
    $body = $this->request($client, 'GET', '/rates?memberId=' . self::MEMBER . '&itemsPerPage=1');
    self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(1, $body['totalItems']);
    self::assertIsArray($body['member']);
    self::assertCount(1, $body['member']);
    self::assertIsArray($body['member'][0]);
    self::assertSame('42.000001', $body['member'][0]['hourlyAmount']);
  }

  #[Test]
  public function operationalMemberCannotReadPrivateRates(): void
  {
    $client = $this->client(self::USER);
    $this->request($client, 'GET', '/rates');
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function operationalMemberCannotConfigurePrivateFinance(): void
  {
    $client = $this->client(self::USER);
    $this->request($client, 'PATCH', '/currency', ['currency' => 'USD']);
    self::assertSame(403, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function outsiderCannotEnumerateOrganizationCurrency(): void
  {
    $client = $this->client('650e8400-e29b-41d4-a716-448040000099');
    $this->request($client, 'GET', '/currency');
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function outsiderCannotAppendARate(): void
  {
    $client = $this->client('650e8400-e29b-41d4-a716-448040000099');
    $this->request($client, 'POST', '/rates', $this->rateInput());
    self::assertSame(404, $client->getResponse()->getStatusCode());
  }

  #[Test]
  public function negativeRateIsRejectedWithoutAWrite(): void
  {
    $client = $this->client();
    $input = $this->rateInput();
    $input['hourlyAmount'] = '-1';
    $this->request($client, 'POST', '/rates', $input);
    self::assertSame(422, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame(0, $this->countRows('maintenance_cost_hourly_rates'));
  }

  private function client(string $actor = self::OWNER): KernelBrowser
  {
    $client = static::createClient();
    $em = $this->main();
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = self::ORG;
    $organization->name = 'Private Cost Finance';
    $organization->slug = 'private-cost-finance';
    $organization->ownerUserId = self::OWNER;
    $organization->createdByUserId = self::OWNER;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $em->persist($organization);
    foreach ([self::OWNER => '650e8400-e29b-41d4-a716-448040000005', self::USER => self::MEMBER] as $userId => $memberId) {
      $member = new OrganizationMemberRecord();
      $member->id = $memberId;
      $member->organization = $organization;
      $member->userId = $userId;
      $member->isActive = true;
      $member->joinedAt = $now;
      $em->persist($member);
      if (self::OWNER === $userId) {
        $role = new OrganizationRoleRecord();
        $role->id = '650e8400-e29b-41d4-a716-448040000006';
        $role->organization = $organization;
        $role->name = 'private_cost_owner';
        $role->permissions = ['*'];
        $role->isSystem = false;
        $role->createdAt = $now;
        $em->persist($role);
        $assignment = new OrganizationMemberRoleRecord();
        $assignment->member = $member;
        $assignment->role = $role;
        $assignment->assignedAt = $now;
        $em->persist($assignment);
      }
    }
    $em->flush();
    $client->loginUser(new SecurityUser($actor, $actor . '@example.com', 'password', ['ROLE_USER']), 'api');

    return $client;
  }

  private function seedRate(): void
  {
    /** @var MaintenanceRateStorePort $rates */
    $rates = self::getContainer()->get(MaintenanceRateStorePort::class);
    $rates->synchronized(self::ORG, static fn () => $rates->append(self::ORG, self::CLIENT, new MaintenanceRateSnapshot(self::RATE, self::MEMBER, '42.000001', 'EUR', '2026-01-01')));
  }

  private function main(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $em */
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');

    return $em;
  }

  /**
   * @return array<string,string>
   */
  private function rateInput(): array
  {
    return ['clientId' => self::CLIENT, 'memberId' => self::MEMBER, 'hourlyAmount' => '42.000001', 'effectiveFrom' => '2026-01-01'];
  }

  /**
   * @param array<string,string>|null $body
   *
   * @return array<string,mixed>
   */
  private function request(KernelBrowser $client, string $method, string $suffix, ?array $body = null): array
  {
    $client->request($method, '/api/organizations/' . self::ORG . '/maintenance-cost' . $suffix, server: [
      'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
      'HTTP_ACCEPT' => 'application/ld+json',
    ], content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
    /** @var array<string,mixed> $decoded */
    $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
  }

  private function countRows(string $table): int
  {
    /** @var int|numeric-string $count */
    $count = $this->main()->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE organization_id = :org', ['org' => self::ORG]);

    return (int) $count;
  }
}
