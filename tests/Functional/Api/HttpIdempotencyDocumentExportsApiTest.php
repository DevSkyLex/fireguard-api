<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord, PlanRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{Connection as QueueConnection, DoctrineTransport};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/** Proves original CSV/PDF bytes, durable audit intent and fresh READ gates across real HTTP export commits. @category Functional Test */
#[SkipDatabaseRollback]
final class HttpIdempotencyDocumentExportsApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'ef000000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = 'ef000000-0000-4000-8000-000000000002';

  private const string ROLE = 'ef000000-0000-4000-8000-000000000003';

  private const string EQUIPMENT = 'ef000000-0000-4000-8000-000000000004';

  private const string INSPECTION = 'ef000000-0000-4000-8000-000000000006';

  private const string INTERVENTION = 'ef000000-0000-4000-8000-000000000007';

  /**
   * @return iterable<string, array{string, string, string}> owned effectful GETs
   */
  public static function exports(): iterable
  {
    $org = '/api/organizations/' . self::ORGANIZATION;
    yield 'inspection CSV' => [$org . '/inspections/export', 'text/csv; charset=utf-8', 'organization.inspection.read'];
    yield 'non-conformities CSV' => [$org . '/non-conformities/export', 'text/csv; charset=utf-8', 'organization.inspection.read'];
    yield 'inspection PDF' => [$org . '/inspections/' . self::INSPECTION . '/report', 'application/pdf', 'organization.inspection.read'];
    yield 'non-conformities PDF' => [$org . '/non-conformities/report', 'application/pdf', 'organization.inspection.read'];
    yield 'intervention CSV' => ['/api/interventions/export?organization=' . $org, 'text/csv; charset=utf-8', 'organization.interventions.read'];
    yield 'intervention PDF' => ['/api/interventions/' . self::INTERVENTION . '/report', 'application/pdf', 'organization.interventions.read'];
  }

  #[Test]
  #[DataProvider('exports')]
  public function exportReplaysOriginalBytesAndOneAuditOutboxThenDeniesRevokedRead(string $uri, string $mime, string $permission): void
  {
    $client = self::createClient();
    $this->seed($client);
    $client->catchExceptions(false);
    $headers = ['HTTP_ACCEPT' => $mime, 'HTTP_IDEMPOTENCY_KEY' => 'original-export-document'];
    $before = $this->main()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'");
    self::assertIsInt($before);
    $client->request('GET', $uri, server: $headers);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Content-Type', $mime);
    $bytes = $client->getInternalResponse()->getContent();
    self::assertNotSame('', $bytes);
    $fileName = $client->getResponse()->headers->get('Content-Disposition');
    self::assertIsString($fileName);
    $payload = $this->main()->fetchOne("SELECT result_payload FROM http_idempotency_receipts WHERE state = 'completed'");
    self::assertIsString($payload);
    self::assertSame($before + 1, $this->main()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));
    $this->main()->executeStatement('UPDATE inspections SET inspector_name = ? WHERE id = ?', ['Later inspector', self::INSPECTION]);
    $this->main()->executeStatement('UPDATE interventions SET name = ? WHERE id = ?', ['Later intervention', self::INTERVENTION]);
    $client->request('GET', $uri, server: $headers);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertResponseHeaderSame('Content-Type', $mime);
    self::assertResponseHeaderSame('Content-Disposition', $fileName);
    self::assertSame($bytes, $client->getInternalResponse()->getContent());
    self::assertSame($payload, $this->main()->fetchOne("SELECT result_payload FROM http_idempotency_receipts WHERE state = 'completed'"));
    self::assertSame($before + 1, $this->main()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));
    $client->catchExceptions(true);
    $this->main()->executeStatement('UPDATE organization_roles SET permissions = ? WHERE id = ?', ['["organization.read"]', self::ROLE]);
    $client->request('GET', $uri, server: $headers);
    self::assertResponseStatusCodeSame(403, 'Revoked ' . $permission . ' must deny before document decode.');
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertNotSame($bytes, $client->getInternalResponse()->getContent());
    self::assertSame($before + 1, $this->main()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));
  }

  #[Test]
  #[DataProvider('exports')]
  public function strictExportEnforcementRejectsAMissingKeyBeforeRendering(string $uri, string $mime, string $permission): void
  {
    $client = self::createClient();
    $this->seed($client);
    $this->enableStrictHeaderEnforcement();
    $before = $this->main()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'");
    $receipts = $this->main()->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts');
    $client->request('GET', $uri, server: ['HTTP_ACCEPT' => $mime]);
    self::assertResponseStatusCodeSame(400, 'The effectful GET with ' . $permission . ' must require its logical key.');
    self::assertStringContainsString('idempotency_key_required', $client->getInternalResponse()->getContent());
    self::assertFalse($client->getResponse()->headers->has('Content-Disposition'));
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertSame($before, $this->main()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));
    self::assertSame($receipts, $this->main()->fetchOne('SELECT COUNT(*) FROM http_idempotency_receipts'));
  }

  private function enableStrictHeaderEnforcement(): void
  {
    $dispatcher = self::getContainer()->get('event_dispatcher');
    self::assertInstanceOf(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class, $dispatcher);
    $previous = $this->service(\Shared\Infrastructure\Idempotency\HttpIdempotencySubscriber::class);
    $dispatcher->removeSubscriber($previous);
    $dispatcher->addSubscriber(new \Shared\Infrastructure\Idempotency\HttpIdempotencySubscriber(
      $this->service(\Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry::class),
      $this->service(\Shared\Infrastructure\Idempotency\HttpIdempotencyPolicyRegistry::class),
      $this->service(\Shared\Infrastructure\Idempotency\HttpIdempotencyEngineCatalog::class),
      $this->service(\Shared\Infrastructure\Idempotency\HttpIdempotencyRequestInput::class),
      $this->service(\Shared\Infrastructure\Idempotency\HttpIdempotencyExecutionContext::class),
      $this->service(\Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyPrincipalPort::class),
      $this->service(\ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface::class),
      true,
      $this->service(\Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyAuthorizationEvidencePort::class),
    ));
  }

  /**
   * @template T of object
   *
   * @param class-string<T> $class required runtime capability
   *
   * @return T the exact configured capability
   */
  private function service(string $class): object
  {
    $service = self::getContainer()->get($class);
    self::assertInstanceOf($class, $service);

    return $service;
  }

  private function main(): Connection
  {
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);

    return $connection;
  }

  private function seed(KernelBrowser $client): void
  {
    $client->disableReboot();
    $auth = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $sender = new DoctrineTransport(new QueueConnection(['queue_name' => 'main_outbox', 'auto_setup' => false, 'use_notify' => false], $main->getConnection()), new PhpSerializer());
    self::getContainer()->set('messenger.transport.main_outbox', $sender);
    $sender->setup();
    $now = new DateTimeImmutable();
    $user = new UserRecord();
    $user->id = self::USER;
    $user->username = 'idempotency-http-test';
    $user->email = 'idempotency-http@example.test';
    $user->firstName = 'HTTP';
    $user->lastName = 'Replay';
    $user->status = 'active';
    $user->emailVerified = true;
    $user->emailOwnershipVerifiedAt = $now;
    $user->createdAt = $now;
    $auth->persist($user);
    $auth->flush();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'HTTP idempotency';
    $organization->slug = 'http-idempotency-test';
    $organization->ownerUserId = 'ef000000-0000-4000-8000-000000000099';
    $organization->createdByUserId = self::USER;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $plan = $main->getRepository(PlanRecord::class)->findOneBy(['key' => 'pro']);
    self::assertInstanceOf(PlanRecord::class, $plan);
    $organization->planId = $plan->id;
    $main->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE;
    $role->organization = $organization;
    $role->name = 'http-replay-role';
    $role->permissions = ['*'];
    $role->createdAt = $now;
    $main->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = 'ef000000-0000-4000-8000-000000000005';
    $member->organization = $organization;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = $now;
    $main->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $main->persist($assignment);
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organizationId = self::ORGANIZATION;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'in_stock';
    $equipment->recordStatus = 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $main->persist($equipment);
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION;
    $inspection->organizationId = self::ORGANIZATION;
    $inspection->equipmentId = self::EQUIPMENT;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Original export inspector';
    $inspection->result = 'pass';
    $inspection->status = 'closed';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $main->persist($inspection);
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION;
    $intervention->organizationId = self::ORGANIZATION;
    $intervention->name = 'Original export intervention';
    $intervention->number = 912;
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $main->persist($intervention);
    $main->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(self::getContainer(), self::USER, $user->email));
  }
}
