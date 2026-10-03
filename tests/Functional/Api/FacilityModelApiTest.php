<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Helper\GlbFixture;

use function file_put_contents;
use function json_decode;
use function json_encode;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Test FacilityModelApiTest. Real multipart HTTP contract, optimistic concurrency and scope denial.
 *
 * @category Functional Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityModelApiTest extends WebTestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655470001';

  private const string ADMIN_USER_ID = '990e8400-e29b-41d4-a716-446655470002';

  private const string PLAIN_MEMBER_USER_ID = '990e8400-e29b-41d4-a716-446655470003';

  private const string OUTSIDER_ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655470004';

  private const string OUTSIDER_USER_ID = '990e8400-e29b-41d4-a716-446655470005';

  private const string BUILDING_ID = '990e8400-e29b-41d4-a716-446655470010';

  private const string ROOM_ID = '990e8400-e29b-41d4-a716-446655470011';

  private const string OUTSIDER_BUILDING_ID = '990e8400-e29b-41d4-a716-446655470012';

  private const string SITE_ID = '990e8400-e29b-41d4-a716-446655470013';

  private const string OTHER_BUILDING_ID = '990e8400-e29b-41d4-a716-446655470014';

  private const string OUTSIDER_SITE_ID = '990e8400-e29b-41d4-a716-446655470015';

  private ?string $requestUserId = null;

  private int $requestCount = 0;

  #[Test]
  public function uploadSettingsActivationReplacementDownloadAndDeletion(): void
  {
    $client = $this->client();
    $first = $this->upload($client);
    self::assertSame(201, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    self::assertSame('"revision-1"', $client->getResponse()->headers->get('ETag'));
    self::assertIsString($first['id']);
    self::assertSame('/api/facility-models/' . $first['id'], $client->getResponse()->headers->get('Location'));
    self::assertSame('model/gltf-binary', $first['mimeType']);
    self::assertSame(1, $first['nodeCount']);
    self::assertFalse($first['active']);
    self::assertSame([], $first['bindings']);
    self::assertIsString($first['id']);
    $id = $first['id'];
    $this->request($client, 'GET', '/api/facility-models/' . $id);
    self::assertResponseIsSuccessful();
    self::assertEqualsCanonicalizing([['index' => 0, 'name' => 'Building shell']], $this->json($client)['nodes']);
    $this->request($client, 'GET', $this->collection());
    self::assertResponseIsSuccessful();
    $list = $this->json($client);
    self::assertSame(1, $list['totalItems']);
    $settings = ['transform' => ['scale' => 2, 'rotationDegrees' => 90, 'translation' => ['x' => 1, 'y' => -3, 'z' => 2]],
      'bindings' => [['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]]];
    $this->patch($client, $id, 1, $settings);
    self::assertResponseIsSuccessful();
    self::assertSame(2, $this->json($client)['revision']);
    self::assertSame([['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]], $this->json($client)['bindings']);
    $this->patch($client, $id, 1, $settings);
    self::assertResponseStatusCodeSame(412);
    $this->request($client, 'PATCH', '/api/facility-models/' . $id, [], [], ['CONTENT_TYPE' => 'application/merge-patch+json'], json_encode($settings, JSON_THROW_ON_ERROR));
    self::assertResponseStatusCodeSame(428);
    $this->request($client, 'POST', '/api/facility-models/' . $id . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-2"']);
    self::assertResponseIsSuccessful();
    self::assertTrue($this->json($client)['active']);
    self::assertSame(3, $this->json($client)['revision']);
    $replacement = $this->upload($client);
    self::assertResponseStatusCodeSame(201);
    self::assertSame([], $replacement['bindings']);
    self::assertIsString($replacement['id']);
    $secondId = $replacement['id'];
    $this->request($client, 'GET', '/api/facility-models/' . $id);
    self::assertTrue($this->json($client)['active'], 'Uploading a replacement must preserve the active model.');
    $this->upload($client);
    self::assertResponseStatusCodeSame(409);
    $this->request($client, 'POST', '/api/facility-models/' . $secondId . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertResponseIsSuccessful();
    self::assertTrue($this->json($client)['active']);
    $this->request($client, 'GET', '/api/facility-models/' . $id);
    self::assertFalse($this->json($client)['active']);
    self::assertSame(4, $this->json($client)['revision']);
    $this->request($client, 'GET', '/api/facility-models/' . $secondId . '/download');
    self::assertResponseIsSuccessful();
    self::assertSame(GlbFixture::contents(), $client->getResponse()->getContent());
    self::assertStringContainsString('attachment;', (string) $client->getResponse()->headers->get('Content-Disposition'));
    self::assertSame('nosniff', $client->getResponse()->headers->get('X-Content-Type-Options'));
    $this->request($client, 'DELETE', '/api/facility-models/' . $id, [], [], ['HTTP_IF_MATCH' => '"revision-3"']);
    self::assertResponseStatusCodeSame(412);
    $this->request($client, 'DELETE', '/api/facility-models/' . $id, [], [], ['HTTP_IF_MATCH' => '"revision-4"']);
    self::assertResponseStatusCodeSame(204);
    $this->request($client, 'DELETE', '/api/facility-models/' . $secondId, [], [], ['HTTP_IF_MATCH' => '"revision-2"']);
    self::assertResponseStatusCodeSame(204);
    $this->request($client, 'GET', '/api/facility-models/' . $secondId . '/download');
    self::assertResponseStatusCodeSame(404);
  }

  #[Test]
  public function rejectsInvalidUploadsAndAssociationsWithoutChangingTheRevision(): void
  {
    $client = $this->client();
    $document = GlbFixture::document();
    $document['extensionsRequired'] = ['KHR_draco_mesh_compression'];
    $this->upload($client, GlbFixture::contents($document));
    self::assertResponseStatusCodeSame(422);
    $this->upload($client, str_repeat('x', 10 * 1024 * 1024 + 1));
    self::assertResponseStatusCodeSame(422);
    $model = $this->upload($client);
    self::assertResponseStatusCodeSame(201);
    self::assertIsString($model['id']);
    self::assertIsArray($model['transform']);
    $settings = ['transform' => $model['transform'], 'bindings' => [['nodeIndex' => 0, 'facilityId' => self::OUTSIDER_BUILDING_ID]]];
    $this->patch($client, $model['id'], 1, $settings);
    self::assertResponseStatusCodeSame(422);
    $settings['bindings'] = [['nodeIndex' => 0, 'facilityId' => self::BUILDING_ID], ['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]];
    $this->patch($client, $model['id'], 1, $settings);
    self::assertResponseStatusCodeSame(422);
    $settings['bindings'] = [];
    $settings['transform']['scale'] = 0;
    $this->patch($client, $model['id'], 1, $settings);
    self::assertResponseStatusCodeSame(422);
    $this->request($client, 'GET', '/api/facility-models/' . $model['id']);
    self::assertSame(1, $this->json($client)['revision']);
  }

  #[Test]
  public function movingABoundTargetMasksItAndPreservesItsHistoricalAssociationUntilExplicitRemoval(): void
  {
    $client = $this->client();
    $model = $this->upload($client);
    self::assertIsString($model['id']);
    self::assertIsArray($model['transform']);
    $id = $model['id'];
    $bindings = [['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]];
    $this->patch($client, $id, 1, ['transform' => $model['transform'], 'bindings' => $bindings]);
    self::assertResponseIsSuccessful();
    $this->request($client, 'POST', '/api/facility-models/' . $id . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-2"']);
    self::assertResponseIsSuccessful();
    $this->request(
      $client,
      'POST',
      '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::ROOM_ID . '/move',
      [],
      [],
      ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-1"'],
      json_encode(['parentFacilityId' => self::OTHER_BUILDING_ID], JSON_THROW_ON_ERROR),
    );
    self::assertResponseIsSuccessful();
    $this->request($client, 'GET', '/api/facility-models/' . $id);
    self::assertResponseIsSuccessful();
    $read = $this->json($client);
    self::assertSame([], $read['bindings']);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $read['bindingIssues']);
    self::assertTrue($read['active']);
    self::assertSame($bindings, $this->storedBindings($id));
    $this->request($client, 'GET', $this->collection());
    $list = $this->json($client);
    self::assertIsArray($list['member']);
    self::assertIsArray($list['member'][0]);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $list['member'][0]['bindingIssues']);
    $this->request($client, 'POST', '/api/facility-models/' . $id . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-3"']);
    self::assertResponseStatusCodeSame(422);
    $this->patch($client, $id, 3, ['transform' => $model['transform']]);
    self::assertResponseIsSuccessful();
    self::assertSame(4, $this->json($client)['revision']);
    self::assertSame($bindings, $this->storedBindings($id));
    $this->patch($client, $id, 4, ['transform' => $model['transform'], 'bindings' => null]);
    self::assertResponseIsSuccessful();
    self::assertSame($bindings, $this->storedBindings($id));
    $this->patch($client, $id, 5, ['transform' => $model['transform'], 'removeBindingNodeIndices' => [0]]);
    self::assertResponseIsSuccessful();
    self::assertSame([], $this->json($client)['bindingIssues']);
    self::assertSame([], $this->storedBindings($id));
  }

  #[Test]
  public function editingAnotherNodeKeepsMaskedReferencesUntilReassignmentOrClearAll(): void
  {
    $client = $this->client();
    $document = GlbFixture::document();
    $document['nodes'] = [['name' => 'room', 'mesh' => 0], ['name' => 'shell', 'mesh' => 0]];
    $document['scenes'] = [['nodes' => [0, 1]]];
    $model = $this->upload($client, GlbFixture::contents($document));
    self::assertIsString($model['id']);
    self::assertIsArray($model['transform']);
    $id = $model['id'];
    $this->patch($client, $id, 1, ['transform' => $model['transform'], 'bindings' => [['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]]]);
    self::assertResponseIsSuccessful();
    $this->mainEntityManager()->getConnection()->executeStatement('DELETE FROM facilities WHERE id = :id', ['id' => self::ROOM_ID]);
    $this->patch($client, $id, 2, ['transform' => $model['transform'], 'bindings' => [['nodeIndex' => 1, 'facilityId' => self::BUILDING_ID]]]);
    self::assertResponseIsSuccessful();
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $this->json($client)['bindingIssues']);
    self::assertSame([['nodeIndex' => 1, 'facilityId' => self::BUILDING_ID], ['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]], $this->storedBindings($id));
    $this->patch($client, $id, 3, ['transform' => $model['transform'], 'bindings' => [['nodeIndex' => 0, 'facilityId' => self::BUILDING_ID], ['nodeIndex' => 1, 'facilityId' => self::BUILDING_ID]]]);
    self::assertResponseIsSuccessful();
    self::assertSame([], $this->json($client)['bindingIssues']);
    $this->patch($client, $id, 4, ['transform' => $model['transform'], 'bindings' => []]);
    self::assertResponseIsSuccessful();
    self::assertSame([], $this->storedBindings($id));
  }

  #[Test]
  public function archivedPublishedTargetsRemainUsableAssociations(): void
  {
    $client = $this->client();
    $model = $this->upload($client);
    self::assertIsString($model['id']);
    $this->mainEntityManager()->getConnection()->executeStatement("UPDATE facilities SET status = 'archived' WHERE id = :id", ['id' => self::ROOM_ID]);
    $bindings = [['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]];
    $this->patch($client, $model['id'], 1, ['transform' => $model['transform'], 'bindings' => $bindings]);
    self::assertResponseIsSuccessful();
    self::assertSame($bindings, $this->json($client)['bindings']);
    self::assertSame([], $this->json($client)['bindingIssues']);
    $this->request($client, 'POST', '/api/facility-models/' . $model['id'] . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-2"']);
    self::assertResponseIsSuccessful();
  }

  #[Test]
  public function nestedLegacyBuildingsUseTheirOwnFrameAndMaskRetainedBindings(): void
  {
    $client = $this->client();
    $model = $this->upload($client);
    self::assertIsString($model['id']);
    $bindings = [['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]];
    $this->patch($client, $model['id'], 1, ['transform' => $model['transform'], 'bindings' => $bindings]);
    self::assertResponseIsSuccessful();
    $this->request($client, 'POST', '/api/facility-models/' . $model['id'] . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-2"']);
    self::assertResponseIsSuccessful();
    $connection = $this->mainEntityManager()->getConnection();
    // Represents an existing atypical hierarchy; new building edges obey the strict taxonomy.
    $connection->executeStatement('UPDATE facilities SET parent_facility_id = :parent WHERE id = :id', ['parent' => self::BUILDING_ID, 'id' => self::OTHER_BUILDING_ID]);
    $connection->executeStatement('UPDATE facilities SET parent_facility_id = :parent WHERE id = :id', ['parent' => self::OTHER_BUILDING_ID, 'id' => self::ROOM_ID]);
    $this->request($client, 'GET', '/api/facility-models/' . $model['id']);
    self::assertResponseIsSuccessful();
    self::assertTrue($this->json($client)['active']);
    self::assertSame([], $this->json($client)['bindings']);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $this->json($client)['bindingIssues']);
    self::assertSame($bindings, $this->storedBindings($model['id']));
    // An unchanged association may be retained while the transform is edited.
    $this->patch($client, $model['id'], 3, ['transform' => $model['transform'], 'bindings' => $bindings]);
    self::assertResponseIsSuccessful();
    self::assertSame($bindings, $this->storedBindings($model['id']));
    $this->request($client, 'POST', '/api/facility-models/' . $model['id'] . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-4"']);
    self::assertResponseStatusCodeSame(422);
    $replacement = $this->upload($client);
    self::assertIsString($replacement['id']);
    $this->patch($client, $replacement['id'], 1, ['transform' => $replacement['transform'], 'bindings' => $bindings]);
    self::assertResponseStatusCodeSame(422);
    $this->patch($client, $replacement['id'], 1, ['transform' => $replacement['transform'], 'bindings' => [['nodeIndex' => 0, 'facilityId' => self::OTHER_BUILDING_ID]]]);
    self::assertResponseStatusCodeSame(422);
  }

  #[Test]
  #[DataProvider('unavailableTargetStates')]
  public function missingForeignAndDraftTargetsProduceTheSameSafeReadIssue(string $state): void
  {
    $client = $this->client();
    $model = $this->upload($client);
    self::assertIsString($model['id']);
    $bindings = [['nodeIndex' => 0, 'facilityId' => self::ROOM_ID]];
    $this->patch($client, $model['id'], 1, ['transform' => $model['transform'], 'bindings' => $bindings]);
    self::assertResponseIsSuccessful();
    $connection = $this->mainEntityManager()->getConnection();
    if ('deleted' === $state) {
      $connection->executeStatement('DELETE FROM facilities WHERE id = :id', ['id' => self::ROOM_ID]);
    } elseif ('foreign' === $state) {
      $connection->executeStatement('UPDATE facilities SET organization_id = :organization WHERE id = :id', ['id' => self::ROOM_ID, 'organization' => self::OUTSIDER_ORGANIZATION_ID]);
    } else {
      $connection->executeStatement("UPDATE facilities SET record_status = 'draft' WHERE id = :id", ['id' => self::ROOM_ID]);
    }
    $this->request($client, 'GET', '/api/facility-models/' . $model['id']);
    self::assertResponseIsSuccessful();
    self::assertSame([], $this->json($client)['bindings']);
    self::assertSame([['nodeIndex' => 0, 'code' => 'target_unavailable']], $this->json($client)['bindingIssues']);
    self::assertStringNotContainsString(self::ROOM_ID, (string) $client->getResponse()->getContent());
    self::assertSame($bindings, $this->storedBindings($model['id']));
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function unavailableTargetStates(): iterable
  {
    yield 'deleted' => ['deleted'];
    yield 'foreign organization' => ['foreign'];
    yield 'draft' => ['draft'];
  }

  /**
   * @param list<mixed> $indices
   */
  #[Test]
  #[DataProvider('invalidRemovedNodeIndices')]
  public function rejectsInvalidOrAmbiguousExplicitRemovalWithoutChangingRevision(array $indices, bool $alsoAssign): void
  {
    $client = $this->client();
    $model = $this->upload($client);
    self::assertIsString($model['id']);
    $settings = ['transform' => $model['transform'], 'removeBindingNodeIndices' => $indices];
    if ($alsoAssign) {
      $settings['bindings'] = [['nodeIndex' => 0, 'facilityId' => self::BUILDING_ID]];
    }
    $this->patch($client, $model['id'], 1, $settings);
    self::assertResponseStatusCodeSame(422);
    $this->request($client, 'GET', '/api/facility-models/' . $model['id']);
    self::assertSame(1, $this->json($client)['revision']);
    self::assertSame([], $this->storedBindings($model['id']));
  }

  /**
   * @return iterable<string, array{list<mixed>, bool}>
   */
  public static function invalidRemovedNodeIndices(): iterable
  {
    yield 'negative' => [[-1], false];
    yield 'outside node count' => [[1], false];
    yield 'duplicate' => [[0, 0], false];
    yield 'string index' => [['0'], false];
    yield 'remove and assign' => [[0], true];
  }

  #[Test]
  #[DataProvider('deniedUsers')]
  public function allEndpointsEnforceFacilitiesGrantsAndOrganizationScope(string $userId, int $status): void
  {
    $client = $this->client();
    $model = $this->upload($client);
    self::assertResponseStatusCodeSame(201);
    self::assertIsString($model['id']);
    $this->loginAs($client, $userId);
    $this->upload($client);
    self::assertResponseStatusCodeSame($status);
    $this->request($client, 'GET', $this->collection());
    self::assertResponseStatusCodeSame($status);
    $this->request($client, 'GET', '/api/facility-models/' . $model['id']);
    self::assertResponseStatusCodeSame($status);
    $this->request($client, 'GET', '/api/facility-models/' . $model['id'] . '/download');
    self::assertResponseStatusCodeSame($status);
    $this->patch($client, $model['id'], 1, ['transform' => $model['transform'], 'bindings' => []]);
    self::assertResponseStatusCodeSame($status);
    $this->request($client, 'POST', '/api/facility-models/' . $model['id'] . '/activate', [], [], ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertResponseStatusCodeSame($status);
    $this->request($client, 'DELETE', '/api/facility-models/' . $model['id'], [], [], ['HTTP_IF_MATCH' => '"revision-1"']);
    self::assertResponseStatusCodeSame($status);
  }

  /**
   * @return iterable<string, array{string, int}>
   */
  public static function deniedUsers(): iterable
  {
    yield 'member without facilities grants' => [self::PLAIN_MEMBER_USER_ID, 403];
    yield 'outside organization' => [self::OUTSIDER_USER_ID, 404];
  }

  #[Test]
  public function allEndpointsRequireAuthentication(): void
  {
    $client = static::createClient();
    foreach ([['GET', $this->collection()], ['POST', $this->collection()],
      ['GET', '/api/facility-models/' . self::BUILDING_ID], ['PATCH', '/api/facility-models/' . self::BUILDING_ID],
      ['DELETE', '/api/facility-models/' . self::BUILDING_ID], ['POST', '/api/facility-models/' . self::BUILDING_ID . '/activate'],
      ['GET', '/api/facility-models/' . self::BUILDING_ID . '/download']] as [$verb, $url]) {
      $this->request($client, $verb, $url);
      self::assertResponseStatusCodeSame(401);
    }
  }

  private function client(): KernelBrowser
  {
    $client = static::createClient();
    $client->disableReboot();
    $this->seedOrganization();
    $this->seedFacilities();
    $this->loginAs($client, self::ADMIN_USER_ID);

    return $client;
  }

  /** @param array<string, mixed> $parameters
   * @param array<string, mixed> $files
   * @param array<string, mixed> $server
   */
  private function request(KernelBrowser &$client, string $method, string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null): void
  {
    if ($this->requestCount > 0) {
      static::ensureKernelShutdown();
      $client = static::createClient();
    }
    ++$this->requestCount;
    if (null !== $this->requestUserId) {
      $this->loginAs($client, $this->requestUserId);
    }
    $client->request($method, $uri, $parameters, $files, $server, $content);
  }

  private function collection(): string
  {
    return '/api/organizations/' . self::ORGANIZATION_ID . '/facilities/' . self::BUILDING_ID . '/models';
  }

  /**
   * @return array<string, mixed>
   */
  private function upload(KernelBrowser &$client, ?string $contents = null): array
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-model-');
    self::assertIsString($path);
    file_put_contents($path, $contents ?? GlbFixture::contents());

    try {
      $file = new UploadedFile($path, 'Building.glb', 'model/gltf-binary', null, true);
      $this->request($client, 'POST', $this->collection(), [], ['file' => $file]);

      return $this->json($client);
    } finally {
      unlink($path);
    }
  }

  /**
   * @param array<string, mixed> $settings
   */
  private function patch(KernelBrowser &$client, string $id, int $revision, array $settings): void
  {
    $this->request(
      $client,
      'PATCH',
      '/api/facility-models/' . $id,
      [],
      [],
      ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"revision-' . $revision . '"'],
      json_encode($settings, JSON_THROW_ON_ERROR),
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function json(KernelBrowser $client): array
  {
    $json = json_decode((string) $client->getResponse()->getContent(), true);
    self::assertIsArray($json);

    /** @var array<string, mixed> $json */
    return $json;
  }

  private function mainEntityManager(): EntityManagerInterface
  {
    $manager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);

    return $manager;
  }

  /**
   * @return list<array{nodeIndex: int, facilityId: string}>
   */
  private function storedBindings(string $id): array
  {
    $stored = $this->mainEntityManager()->getConnection()->fetchOne('SELECT bindings FROM facility_models WHERE id = :id', ['id' => $id]);
    self::assertIsString($stored);
    $bindings = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($bindings);

    /** @var list<array{nodeIndex: int, facilityId: string}> $bindings */
    return $bindings;
  }

  private function loginAs(KernelBrowser $client, string $userId): void
  {
    $this->requestUserId = $userId;
    $client->loginUser(new SecurityUser(id: $userId, email: 'model-' . $userId . '@example.com', password: 'hashed', roles: ['ROLE_USER']), 'api');
  }

  private function seedFacilities(): void
  {
    $em = $this->mainEntityManager();
    $site = new FacilityRecord();
    $outsiderSite = new FacilityRecord();
    $building = new FacilityRecord();
    foreach ([[$site, self::SITE_ID, self::ORGANIZATION_ID, 'site', null],
      [$outsiderSite, self::OUTSIDER_SITE_ID, self::OUTSIDER_ORGANIZATION_ID, 'site', null],
      [$building, self::BUILDING_ID, self::ORGANIZATION_ID, 'building', $site],
      [new FacilityRecord(), self::OTHER_BUILDING_ID, self::ORGANIZATION_ID, 'building', $site],
      [new FacilityRecord(), self::ROOM_ID, self::ORGANIZATION_ID, 'zone', $building],
      [new FacilityRecord(), self::OUTSIDER_BUILDING_ID, self::OUTSIDER_ORGANIZATION_ID, 'building', $outsiderSite]] as [$record, $id, $org, $type, $parent]) {
      $record->id = $id;
      $record->organization = $em->getReference(OrganizationRecord::class, $org);
      $record->parentFacility = $parent;
      $record->type = $type;
      $record->name = 'Model test ' . $type;
      $record->status = 'active';
      $record->metadata = [];
      $record->createdAt = new DateTimeImmutable('2026-10-03');
      $record->updatedAt = $record->createdAt;
      $em->persist($record);
    }
    $em->flush();
  }

  private function seedOrganization(): void
  {
    $entityManager = $this->mainEntityManager();

    foreach ([self::ORGANIZATION_ID, self::OUTSIDER_ORGANIZATION_ID] as $organizationId) {
      $existing = $entityManager->find(OrganizationRecord::class, $organizationId);
      if ($existing instanceof OrganizationRecord) {
        $entityManager->remove($existing);
        $entityManager->flush();
      }
    }

    $now = new DateTimeImmutable('2026-08-30T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Imported Model Test Org';
    $organization->slug = 'imported-model-test-org-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ADMIN_USER_ID;
    $organization->createdByUserId = self::ADMIN_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $entityManager->persist($organization);

    $outsiderOrganization = new OrganizationRecord();
    $outsiderOrganization->id = self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->name = 'Imported Model Outsider Org';
    $outsiderOrganization->slug = 'imported-model-outsider-org-' . self::OUTSIDER_ORGANIZATION_ID;
    $outsiderOrganization->ownerUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->createdByUserId = self::OUTSIDER_USER_ID;
    $outsiderOrganization->status = 'active';
    $outsiderOrganization->isActive = true;
    $outsiderOrganization->createdAt = $now;
    $outsiderOrganization->updatedAt = $now;
    $entityManager->persist($outsiderOrganization);

    $adminRole = new OrganizationRoleRecord();
    $adminRole->id = '990e8400-e29b-41d4-a716-446655470050';
    $adminRole->organization = $organization;
    $adminRole->name = 'imported-model-full-access';
    $adminRole->permissions = ['*'];
    $adminRole->description = 'Functional-test-only role granting every permission.';
    $adminRole->isSystem = false;
    $adminRole->createdAt = $now;
    $entityManager->persist($adminRole);

    $readOnlyRole = new OrganizationRoleRecord();
    $readOnlyRole->id = '990e8400-e29b-41d4-a716-446655470051';
    $readOnlyRole->organization = $organization;
    $readOnlyRole->name = 'imported-model-no-facilities-access';
    $readOnlyRole->permissions = ['organization.read'];
    $readOnlyRole->description = 'Functional-test-only role without organization.facilities.read.';
    $readOnlyRole->isSystem = false;
    $readOnlyRole->createdAt = $now;
    $entityManager->persist($readOnlyRole);

    $outsiderRole = new OrganizationRoleRecord();
    $outsiderRole->id = '990e8400-e29b-41d4-a716-446655470052';
    $outsiderRole->organization = $outsiderOrganization;
    $outsiderRole->name = 'imported-model-outsider-full-access';
    $outsiderRole->permissions = ['*'];
    $outsiderRole->description = 'Functional-test-only role for the unrelated organization.';
    $outsiderRole->isSystem = false;
    $outsiderRole->createdAt = $now;
    $entityManager->persist($outsiderRole);

    $adminMember = new OrganizationMemberRecord();
    $adminMember->id = '990e8400-e29b-41d4-a716-446655470060';
    $adminMember->organization = $organization;
    $adminMember->userId = self::ADMIN_USER_ID;
    $adminMember->isActive = true;
    $adminMember->joinedAt = $now;
    $entityManager->persist($adminMember);

    $adminAssignment = new OrganizationMemberRoleRecord();
    $adminAssignment->member = $adminMember;
    $adminAssignment->role = $adminRole;
    $adminAssignment->assignedAt = $now;
    $entityManager->persist($adminAssignment);

    $plainMember = new OrganizationMemberRecord();
    $plainMember->id = '990e8400-e29b-41d4-a716-446655470061';
    $plainMember->organization = $organization;
    $plainMember->userId = self::PLAIN_MEMBER_USER_ID;
    $plainMember->isActive = true;
    $plainMember->joinedAt = $now;
    $entityManager->persist($plainMember);

    $plainAssignment = new OrganizationMemberRoleRecord();
    $plainAssignment->member = $plainMember;
    $plainAssignment->role = $readOnlyRole;
    $plainAssignment->assignedAt = $now;
    $entityManager->persist($plainAssignment);

    $outsiderMember = new OrganizationMemberRecord();
    $outsiderMember->id = '990e8400-e29b-41d4-a716-446655470062';
    $outsiderMember->organization = $outsiderOrganization;
    $outsiderMember->userId = self::OUTSIDER_USER_ID;
    $outsiderMember->isActive = true;
    $outsiderMember->joinedAt = $now;
    $entityManager->persist($outsiderMember);

    $outsiderAssignment = new OrganizationMemberRoleRecord();
    $outsiderAssignment->member = $outsiderMember;
    $outsiderAssignment->role = $outsiderRole;
    $outsiderAssignment->assignedAt = $now;
    $entityManager->persist($outsiderAssignment);

    $entityManager->flush();
  }
}
