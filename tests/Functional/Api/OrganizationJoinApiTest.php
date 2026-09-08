<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Application\Port\Outbound\JwtTokenServicePort;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Application\Port\Outbound\OrganizationDomainProofPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;
use User\Application\Port\Outbound\UserRepositoryPort;

use function array_keys;
use function json_decode;
use function json_encode;
use function str_pad;

use const JSON_THROW_ON_ERROR;
use const STR_PAD_LEFT;

/**
 * HTTP contracts and denial paths with local DNS/email fakes and real main persistence.
 *
 * @category FunctionalTest
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinApiTest extends WebTestCase
{
  private const string ORG = '550e8400-e29b-41d4-a716-446655445701';

  private const string OWNER = '550e8400-e29b-41d4-a716-446655445702';

  private const string APPLICANT = '550e8400-e29b-41d4-a716-446655445703';

  private const string MEMBER_ROLE = '550e8400-e29b-41d4-a716-446655445704';

  private const string ADMIN_ROLE = '550e8400-e29b-41d4-a716-446655445705';

  private ?DateTimeImmutable $proofAt = null;

  /**
   * @var string|null signed bearer credential for the selected fixture actor
   */
  private ?string $accessToken = null;

  #[Test]
  public function discoveryRequiresAuthentication(): void
  {
    $client = static::createClient();
    $client->request('GET', '/api/organizations/join-options');
    self::assertResponseStatusCodeSame(401);
  }

  #[Test]
  public function outsiderCannotReadOrChangePolicy(): void
  {
    $client = $this->setupOrganization();
    $this->login($client, self::APPLICANT);
    $this->call($client, 'GET', '/' . self::ORG . '/access-policy');
    self::assertResponseStatusCodeSame(404);
    $this->call($client, 'PATCH', '/' . self::ORG . '/access-policy', ['mode' => 'invitation_only']);
    self::assertResponseStatusCodeSame(404);
  }

  #[Test]
  public function policyStartsDisabledAndActivationNeedsVerifiedDomain(): void
  {
    $client = $this->setupOrganization();
    $this->call($client, 'GET', '/' . self::ORG . '/access-policy');
    self::assertResponseIsSuccessful();
    self::assertSame('invitation_only', $this->body($client)['mode']);
    $this->call($client, 'PATCH', '/' . self::ORG . '/access-policy', ['mode' => 'automatic', 'roleId' => self::MEMBER_ROLE]);
    self::assertResponseStatusCodeSame(409);
    self::assertSame('organization_join_domain_unavailable', $this->body($client)['code']);
  }

  #[Test]
  public function verifiedDomainRequestCanBeApprovedAndDoesNotExposeDnsToApplicant(): void
  {
    $client = $this->setupOrganization();
    $this->enable($client, 'approval_required');
    $this->login($client, self::APPLICANT);
    $this->call($client, 'GET', '/join-options');
    self::assertResponseIsSuccessful();
    $options = $this->body($client);
    self::assertIsArray($options['organizations']);
    self::assertIsArray($options['organizations'][0]);
    self::assertSame(['request'], $options['organizations'][0]['actions']);
    self::assertArrayNotHasKey('dnsValue', $options['organizations'][0]);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests');
    self::assertResponseIsSuccessful();
    $requestId = $this->body($client)['id'];
    self::assertIsString($requestId);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests');
    self::assertSame($requestId, $this->body($client)['id']);
    $this->login($client, self::OWNER);
    $this->call($client, 'GET', '/' . self::ORG . '/join-requests');
    self::assertResponseIsSuccessful();
    self::assertSame(1, $this->body($client)['totalItems']);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests/' . $requestId . '/approve', ['roleIds' => [self::MEMBER_ROLE]]);
    self::assertResponseIsSuccessful();
    self::assertSame('approved', $this->body($client)['status']);
    $this->login($client, self::APPLICANT);
    $this->call($client, 'GET', '/join-options');
    $opened = $this->body($client);
    self::assertIsArray($opened['organizations']);
    self::assertIsArray($opened['organizations'][0]);
    self::assertSame(['open'], $opened['organizations'][0]['actions']);
  }

  #[Test]
  public function automaticJoinAndRoleGuardUseMemberCeiling(): void
  {
    $client = $this->setupOrganization();
    $this->enable($client, 'automatic');
    $this->call($client, 'PATCH', '/' . self::ORG . '/access-policy', ['mode' => 'automatic', 'roleId' => self::ADMIN_ROLE]);
    self::assertResponseStatusCodeSame(409);
    $this->login($client, self::APPLICANT);
    $this->call($client, 'POST', '/' . self::ORG . '/join');
    self::assertResponseIsSuccessful();
    self::assertSame(self::ORG, $this->body($client)['organizationId']);
    $this->call($client, 'POST', '/' . self::ORG . '/join');
    self::assertResponseIsSuccessful();
  }

  #[Test]
  public function applicantCanCancelAndOutsiderCannotReview(): void
  {
    $client = $this->setupOrganization();
    $this->enable($client, 'approval_required');
    $this->login($client, self::APPLICANT);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests');
    $requestId = $this->body($client)['id'];
    self::assertIsString($requestId);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests/' . $requestId . '/approve', ['roleIds' => [self::MEMBER_ROLE]]);
    self::assertResponseStatusCodeSame(404);
    $this->call($client, 'POST', '/join-requests/' . $requestId . '/cancel');
    self::assertResponseIsSuccessful();
    self::assertSame('cancelled', $this->body($client)['status']);
  }

  #[Test]
  #[DataProvider('inactiveOrganizations')]
  public function applicantCanCancelAfterOrganizationBecomesInactive(string $status): void
  {
    $client = $this->setupOrganization();
    $this->enable($client, 'approval_required');
    $this->login($client, self::APPLICANT);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests');
    self::assertResponseIsSuccessful();
    $requestId = $this->body($client)['id'];
    self::assertIsString($requestId);
    $em = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $em->getConnection()->executeStatement('UPDATE organizations SET status = :status WHERE id = :id', ['status' => $status, 'id' => self::ORG]);
    $em->clear();

    // Even an administrator cannot cancel someone else's account-scoped request.
    $this->login($client, self::OWNER);
    $this->call($client, 'POST', '/join-requests/' . $requestId . '/cancel');
    self::assertResponseStatusCodeSame(404);
    $this->login($client, self::APPLICANT);
    $this->call($client, 'POST', '/join-requests/' . $requestId . '/cancel');
    self::assertResponseIsSuccessful();
    self::assertSame('cancelled', $this->body($client)['status']);
    $this->call($client, 'POST', '/join-requests/' . $requestId . '/cancel');
    self::assertResponseIsSuccessful();
    self::assertSame('cancelled', $this->body($client)['status']);
  }

  /**
   * @return iterable<string, array{string}> organizations still retain pending applications
   */
  public static function inactiveOrganizations(): iterable
  {
    yield 'archived' => ['archived'];
    yield 'suspended' => ['suspended'];
  }

  #[Test]
  public function newAddressProofCannotReviveEarlierRequestEvenWhenAddressMatchesAgain(): void
  {
    $client = $this->setupOrganization();
    $this->enable($client, 'approval_required');
    $this->login($client, self::APPLICANT);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests');
    self::assertResponseIsSuccessful();
    $requestId = $this->body($client)['id'];
    self::assertIsString($requestId);
    // Simulates changing away and back: address matches, but its ownership generation changed.
    $this->proofAt = new DateTimeImmutable();
    $this->login($client, self::OWNER);
    $this->call($client, 'POST', '/' . self::ORG . '/join-requests/' . $requestId . '/approve', ['roleIds' => [self::MEMBER_ROLE]]);
    self::assertResponseStatusCodeSame(409);
    self::assertSame('organization_join_email_changed', $this->body($client)['code']);
    $this->login($client, self::APPLICANT);
    $this->call($client, 'GET', '/join-requests');
    $body = $this->body($client);
    self::assertIsArray($body['member']);
    self::assertIsArray($body['member'][0]);
    self::assertSame('cancelled', $body['member'][0]['status']);
    self::assertSame([], $body['member'][0]['actions']);
  }

  /**
   * @return KernelBrowser isolated app with controllable provider proofs
   */
  private function setupOrganization(): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $container = static::getContainer();
    $emails = $this->createStub(EmailOwnershipPort::class);
    $this->proofAt = new DateTimeImmutable('-1 hour');
    $emails->method('get')->willReturnCallback(fn (string $id) => new EmailOwnershipResult($id, $id . '@corp.example', true, verifiedAt: $this->proofAt));
    $container->set(EmailOwnershipPort::class, $emails);
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('findById')->willReturnCallback(static fn (\User\Domain\ValueObject\UserId $id) => UserTestFactory::createActive((string) $id, (string) $id . '@corp.example'));
    $container->set(UserRepositoryPort::class, $users);
    $dns = $this->createStub(OrganizationDomainProofPort::class);
    $dns->method('normalize')->willReturn('corp.example');
    $dns->method('verify')->willReturn(true);
    $dns->method('challenge')->willReturn('fireguard-verification=test');
    $counter = 0;
    $dns->method('identifier')->willReturnCallback(static function () use (&$counter): string { return '550e8400-e29b-41d4-a716-4466554457' . str_pad((string) (++$counter + 20), 2, '0', STR_PAD_LEFT); });
    $container->set(OrganizationDomainProofPort::class, $dns);
    /** @var EntityManagerInterface $em */
    $em = $container->get('doctrine.orm.main_entity_manager');
    $now = new DateTimeImmutable();
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Join test';
    $org->slug = 'join-test';
    $org->ownerUserId = self::OWNER;
    $org->createdByUserId = self::OWNER;
    $org->createdAt = $now;
    $org->updatedAt = $now;
    $em->persist($org);
    foreach ([[self::ADMIN_ROLE, 'admin', ['organization.*']], [self::MEMBER_ROLE, 'member', ['organization.read']]] as [$id, $name, $permissions]) {
      $role = new OrganizationRoleRecord();
      $role->id = $id;
      $role->organization = $org;
      $role->name = $name;
      $role->permissions = $permissions;
      $role->isSystem = true;
      $role->createdAt = $now;
      $em->persist($role);
      if ('admin' === $name) {
        $member = new OrganizationMemberRecord();
        $member->id = '550e8400-e29b-41d4-a716-446655445706';
        $member->organization = $org;
        $member->userId = self::OWNER;
        $member->isActive = true;
        $member->joinedAt = $now;
        $em->persist($member);
        $assignment = new OrganizationMemberRoleRecord();
        $assignment->member = $member;
        $assignment->role = $role;
        $assignment->assignedAt = $now;
        $em->persist($assignment);
      }
    }
    $em->flush();
    $this->login($client, self::OWNER);

    return $client;
  }

  private function enable(KernelBrowser $client, string $mode): void
  {
    $this->call($client, 'POST', '/' . self::ORG . '/domains', ['domain' => 'corp.example']);
    self::assertResponseIsSuccessful();
    $id = $this->body($client)['id'];
    self::assertIsString($id);
    $this->call($client, 'POST', '/' . self::ORG . '/domains/' . $id . '/verify');
    self::assertResponseIsSuccessful();
    $this->call($client, 'PATCH', '/' . self::ORG . '/access-policy', ['mode' => $mode, 'roleId' => self::MEMBER_ROLE]);
    self::assertResponseIsSuccessful();
  }

  /**
   * @param non-empty-string $userId the selected fixed fixture account identifier
   */
  private function login(KernelBrowser $client, string $userId): void
  {
    self::assertNotSame('', $userId);
    $tokens = static::getContainer()->get(JwtTokenServicePort::class);
    self::assertInstanceOf(JwtTokenServicePort::class, $tokens);
    $this->accessToken = $tokens->generateTokens($userId, $userId . '@corp.example')['access_token'];
  }

  /**
   * @param array<string,mixed>|null $body
   */
  private function call(KernelBrowser $client, string $method, string $path, ?array $body = null): void
  {
    // Stateless authentication must traverse the real bearer authenticator on
    // every request; loginUser's in-memory token is reset by Kernel::boot().
    $server = ['CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json'];
    if (null !== $this->accessToken) {
      $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken;
    }
    $client->request($method, '/api/organizations' . $path, server: $server, content: null === $body ? '{}' : json_encode($body, JSON_THROW_ON_ERROR));
  }

  /**
   * @return array<string,mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($decoded);
    foreach (array_keys($decoded) as $key) {
      self::assertIsString($key);
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
  }
}
