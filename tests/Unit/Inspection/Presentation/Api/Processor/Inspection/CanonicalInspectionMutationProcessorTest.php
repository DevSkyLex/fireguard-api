<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Presentation\Api\Dto\Input\Inspection\PatchCanonicalInspectionInput;
use Inspection\Presentation\Api\Processor\Inspection\CanonicalInspectionMutationProcessor;
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
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

#[CoversClass(CanonicalInspectionMutationProcessor::class)]
final class CanonicalInspectionMutationProcessorTest extends TestCase
{
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440023';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';

  #[Test]
  public function testDeletingDraftInspectionRemovesIt(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('remove')->with($record);
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
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);

    self::assertNull($result);
    self::assertSame(3, $record->revision);
  }

  #[Test]
  public function testRejectsDraftToClosedTransition(): void
  {
    $record = $this->record();
    $record->status = 'draft';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      $entityManager,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testAllowsSubmittedToClosedTransition(): void
  {
    $record = $this->record();

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      $entityManager,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('closed', $record->status);
    self::assertSame(4, $record->revision);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testDraftRecordSkipsStatusTransitionValidation(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;
    $record->status = 'draft';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    // A draft (intervention scratchpad) record may be freely edited — an
    // otherwise-illegal transition (draft -> closed) must not throw.
    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      $entityManager,
      $resources,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('closed', $record->status);
    self::assertSame(4, $result?->revision);
  }

  private function processor(
    InspectionRecord $record,
    RequestStack $requestStack,
    ?EntityManagerInterface $entityManager = null,
    ?InterventionResourceGatewayPort $resources = null,
  ): CanonicalInspectionMutationProcessor {
    $entityManager ??= $this->entityManager($record);
    $resources ??= $this->createStub(InterventionResourceGatewayPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());
    $manager = new InterventionResourceManager($resources);
    $provider = new CanonicalInspectionProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $manager,
    );

    return new CanonicalInspectionMutationProcessor(
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
  private function entityManager(InspectionRecord $record): EntityManagerInterface
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->with(InspectionRecord::class, self::INSPECTION_ID)->willReturn($record);

    return $entityManager;
  }

  private function record(): InspectionRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new InspectionRecord();
    $record->id = self::INSPECTION_ID;
    $record->organization = $organization;
    $record->recordStatus = 'published';
    $record->revision = 3;
    $record->status = 'submitted';
    $record->inspectorType = 'external';
    $record->inspectorName = 'Inspector';
    $record->equipmentId = '550e8400-e29b-41d4-a716-446655440025';
    $record->result = 'pass';
    $record->performedAt = new DateTimeImmutable();
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();
    $record->nonConformities = new ArrayCollection();

    return $record;
  }

  private function request(string $method, ?string $content = null): RequestStack
  {
    $request = Request::create('/api/inspections/' . self::INSPECTION_ID, $method, [], [], [], [], $content);
    $request->headers->set('If-Match', '"revision-3"');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function patchStatus(string $status): PatchCanonicalInspectionInput
  {
    $input = new PatchCanonicalInspectionInput();
    $input->status = $status;

    return $input;
  }

  private function user(): SecurityUser
  {
    return new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true);
  }
}
