<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Dto\Input\Equipment\PatchCanonicalEquipmentInput;
use Equipment\Presentation\Api\Processor\Equipment\CanonicalEquipmentMutationProcessor;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[CoversClass(CanonicalEquipmentMutationProcessor::class)]
final class CanonicalEquipmentMutationProcessorTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testDeletingPublishedEquipmentDecommissionsIt(): void
  {
    $record = $this->record();
    $record->interventionId = self::INTERVENTION_ID;

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );
    $resources->expects(self::once())->method('touchDraftIntervention')->with(self::INTERVENTION_ID);

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
      $resources,
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);

    self::assertNull($result);
    self::assertSame('decommissioned', $record->status);
    self::assertSame(4, $record->revision);
  }

  #[Test]
  public function testMergePatchExplicitNullClearsNullableField(): void
  {
    $record = $this->record();
    $record->serialNumber = 'SN-123';
    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"serialNumber":null}'),
      $entityManager,
    )->process(new PatchCanonicalEquipmentInput(), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertNull($record->serialNumber);
    self::assertSame(4, $record->revision);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testRejectsIllegalPublishedStatusTransition(): void
  {
    $record = $this->record();
    $record->status = 'decommissioned';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"operational"}'),
      $entityManager,
    )->process($this->patchStatus('operational'), new Patch(), ['id' => self::EQUIPMENT_ID]);
  }

  #[Test]
  public function testAllowsLegalPublishedStatusTransition(): void
  {
    $record = $this->record();

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"under_maintenance"}'),
      $entityManager,
    )->process($this->patchStatus('under_maintenance'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('under_maintenance', $record->status);
    self::assertSame(4, $record->revision);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testDraftRecordSkipsStatusTransitionValidation(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;
    $record->status = 'decommissioned';
    $record->facilityId = null;

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    // A draft (intervention scratchpad) record may be freely edited — an
    // otherwise-illegal transition (decommissioned -> in_stock) must not throw.
    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"in_stock"}'),
      $entityManager,
      $resources,
    )->process($this->patchStatus('in_stock'), new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertSame('in_stock', $record->status);
    self::assertSame(4, $result?->revision);
  }

  private function processor(
    EquipmentRecord $record,
    RequestStack $requestStack,
    ?EntityManagerInterface $entityManager = null,
    ?InterventionResourceGatewayPort $resources = null,
  ): CanonicalEquipmentMutationProcessor {
    $entityManager ??= $this->entityManager($record);
    $resources ??= $this->createStub(InterventionResourceGatewayPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());
    $manager = new InterventionResourceManager($resources);
    $provider = new CanonicalEquipmentProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $manager,
    );

    return new CanonicalEquipmentMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $provider,
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
    );
  }

  /**
   * @return EntityManagerInterface&MockObject
   */
  private function entityManager(EquipmentRecord $record): EntityManagerInterface
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->with(EquipmentRecord::class, self::EQUIPMENT_ID)->willReturn($record);

    return $entityManager;
  }

  private function patchStatus(string $status): PatchCanonicalEquipmentInput
  {
    $input = new PatchCanonicalEquipmentInput();
    $input->status = $status;

    return $input;
  }

  private function record(): EquipmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new EquipmentRecord();
    $record->id = self::EQUIPMENT_ID;
    $record->organization = $organization;
    $record->recordStatus = 'published';
    $record->revision = 3;
    $record->type = 'fire_extinguisher';
    $record->status = 'operational';
    $record->facilityId = '550e8400-e29b-41d4-a716-446655440005';
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();

    return $record;
  }

  private function request(string $method, ?string $content = null): RequestStack
  {
    $request = Request::create('/api/equipment/' . self::EQUIPMENT_ID, $method, [], [], [], [], $content);
    $request->headers->set('If-Match', '"revision-3"');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function user(): SecurityUser
  {
    return new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true);
  }
}
