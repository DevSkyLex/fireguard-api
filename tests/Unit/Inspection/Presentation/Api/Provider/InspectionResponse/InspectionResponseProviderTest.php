<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\InspectionResponse;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionResponseRecord;
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Inspection\Presentation\Api\Provider\InspectionResponse\InspectionResponseProvider;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test InspectionResponseProviderTest.
 *
 * Covers the item branch (`GET /api/inspection_responses/{id}`) and the
 * record → output projection. The collection branch builds a live Doctrine
 * query and is left to the functional suite.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionResponseProvider::class)]
final class InspectionResponseProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440701';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440702';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440703';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440704';

  #[Test]
  public function testOutputProjectsTheRecordOntoIris(): void
  {
    $record = $this->record();
    $record->interventionId = 'intervention-1';

    $output = InspectionResponseProvider::output($record);

    self::assertInstanceOf(InspectionResponseOutput::class, $output);
    self::assertSame(self::RESPONSE_ID, $output->id);
    self::assertSame('/api/organizations/' . self::ORG_ID, $output->organization);
    self::assertSame('/api/interventions/intervention-1', $output->intervention);
    self::assertSame('/api/inspections/' . self::INSPECTION_ID, $output->inspection);
    self::assertSame('published', $output->recordStatus);
    self::assertSame(2, $output->revision);
    self::assertSame('item-1', $output->itemKey);
    self::assertSame('ok', $output->value);
    self::assertSame('2026-01-01T08:00:00+00:00', $output->createdAt);
    self::assertSame('2026-01-02T08:00:00+00:00', $output->updatedAt);
  }

  #[Test]
  public function testOutputLeavesTheInterventionNullWhenAbsent(): void
  {
    self::assertNull(InspectionResponseProvider::output($this->record())->intervention);
  }

  #[Test]
  public function testOutputRejectsARecordWithoutAnOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $this->expectException(NotFoundHttpException::class);

    InspectionResponseProvider::output($record);
  }

  #[Test]
  public function testProvideReturnsTheOutputForAKnownIdentifier(): void
  {
    $provider = $this->provider($this->entityManager($this->record()), granted: true);

    $output = $provider->provide(new Get(), ['id' => self::RESPONSE_ID]);

    self::assertInstanceOf(InspectionResponseOutput::class, $output);
    self::assertSame(self::RESPONSE_ID, $output->id);
  }

  #[Test]
  public function testProvideThrowsWhenTheRecordIsUnknown(): void
  {
    $provider = $this->provider($this->entityManager(null), granted: true);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => self::RESPONSE_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenTheReadPermissionIsMissing(): void
  {
    $provider = $this->provider($this->entityManager($this->record()), granted: false);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => self::RESPONSE_ID]);
  }

  private function provider(EntityManagerInterface $entityManager, bool $granted): InspectionResponseProvider
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($granted);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return new InspectionResponseProvider(
      entityManager: $entityManager,
      authorization: $authorization,
      security: $security,
      requestStack: new RequestStack(),
      interventionResourceManager: new InterventionResourceManager($this->createStub(InterventionResourceGatewayPort::class)),
    );
  }

  private function entityManager(?InspectionResponseRecord $record): EntityManagerInterface
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);

    return $entityManager;
  }

  private function record(): InspectionResponseRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORG_ID;

    $record = new InspectionResponseRecord();
    $record->id = self::RESPONSE_ID;
    $record->organization = $organization;
    $record->inspectionId = self::INSPECTION_ID;
    $record->recordStatus = 'published';
    $record->revision = 2;
    $record->itemKey = 'item-1';
    $record->value = 'ok';
    $record->createdAt = new DateTimeImmutable('2026-01-01T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-02T08:00:00+00:00');

    return $record;
  }
}
