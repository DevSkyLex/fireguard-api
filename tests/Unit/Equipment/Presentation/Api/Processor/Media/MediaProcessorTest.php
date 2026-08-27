<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Media;

use ApiPlatform\Metadata\{Delete, Post};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\AddAttachmentResult;
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentCommand;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentRecord};
use Equipment\Presentation\Api\Processor\Media\MediaProcessor;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionResourceManager};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Attachment\AttachmentConstraints;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(MediaProcessor::class)]
final class MediaProcessorTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string CLIENT_ID = '550e8400-e29b-41d4-a716-446655440004';

  /**
   * A real 1x1 GIF. MultipartAttachmentGuard sniffs the real bytes, not the
   * client-supplied mimeType label, so every test whose upload must pass
   * MIME validation needs real image bytes on disk.
   */
  private const string MINIMAL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

  private ?string $uploadPath = null;

  protected function tearDown(): void
  {
    if (null !== $this->uploadPath) {
      unlink($this->uploadPath);
      $this->uploadPath = null;
    }

    parent::tearDown();
  }

  #[Test]
  public function testDeletingMediaFromPublishedEquipmentUsesOperationalPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->interventionId = self::INTERVENTION_ID;
    $equipment->recordStatus = 'published';
    $attachment = new EquipmentAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->equipment = $equipment;
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->with(EquipmentAttachmentRecord::class, $attachment->id)->willReturn($attachment);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::never())->method('flush');
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::never())->method('interventionMutationContext');
    $resources->expects(self::never())->method('touchDraftIntervention');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-id', self::ORGANIZATION_ID, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );
    $requestStack = new RequestStack();
    $request = Request::create('/api/media/' . $attachment->id, 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $requestStack->push($request);
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $command): bool => $command instanceof DeleteAttachmentCommand
          && self::ORGANIZATION_ID === $command->organizationId
          && self::EQUIPMENT_ID === $command->equipmentId
          && 'attachment-id' === $command->attachmentId,
      ));

    $result = new MediaProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($resources),
      new RevisionGuard($requestStack),
      new MultipartAttachmentGuard(),
    )->process(null, new Delete(), ['id' => $attachment->id]);

    self::assertNull($result);
  }

  #[Test]
  public function testInterventionContextAuthorizesEvidenceForPublishedEquipment(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'media-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    try {
      $organization = new OrganizationRecord();
      $organization->id = self::ORGANIZATION_ID;
      $equipment = new EquipmentRecord();
      $equipment->id = self::EQUIPMENT_ID;
      $equipment->organization = $organization;
      $attachment = new EquipmentAttachmentRecord();
      $attachment->id = 'attachment-id';
      $attachment->equipment = $equipment;
      $attachment->fileName = 'photo.jpg';
      $attachment->mimeType = 'image/jpeg';
      $attachment->size = 5;
      $attachment->uploadedAt = new DateTimeImmutable();
      $entityManager = $this->createStub(EntityManagerInterface::class);
      $entityManager->method('wrapInTransaction')->willReturnCallback(
        static fn (callable $callback): mixed => $callback(),
      );
      $entityManager->method('find')->willReturnCallback(
        static fn (string $class): EquipmentRecord|EquipmentAttachmentRecord|null => match ($class) {
          EquipmentRecord::class => $equipment,
          EquipmentAttachmentRecord::class => $attachment,
          default => null,
        },
      );
      $resources = $this->createMock(InterventionResourceGatewayPort::class);
      $context = new InterventionAssignmentContext(
        self::INTERVENTION_ID,
        self::ORGANIZATION_ID,
        'in_progress',
      );
      $resources->method('interventionAssignmentContext')->willReturn($context);
      $resources->method('interventionMutationContext')->willReturn($context);
      $resources->method('resourceInInterventionScope')->willReturn(true);
      $resources->expects(self::once())->method('touchDraftIntervention')->with(self::INTERVENTION_ID);
      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );
      $commandBus = $this->createStub(CommandBusPort::class);
      $commandBus->method('dispatch')->willReturn(new AddAttachmentResult(
        $attachment->id,
        self::EQUIPMENT_ID,
        $attachment->fileName,
        $attachment->mimeType,
        $attachment->size,
        null,
        $attachment->uploadedAt,
      ));
      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/media',
        'POST',
        [
          'equipment' => '/api/equipment/' . self::EQUIPMENT_ID,
          'intervention' => '/api/interventions/' . self::INTERVENTION_ID,
        ],
        [],
        ['file' => new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true)],
      ));

      $result = new MediaProcessor(
        $entityManager,
        $commandBus,
        $authorization,
        $security,
        $requestStack,
        new InterventionResourceManager($resources),
        new RevisionGuard($requestStack),
        new MultipartAttachmentGuard(),
      )->process(null, new Post());

      self::assertSame($attachment->id, $result?->id);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadRejectsAMissingEquipmentOrFile(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Multipart fields "equipment" and "file" are required.');

    $this->processor(requestStack: $this->uploadRequest(withFile: false))->process(null, new Post());
  }

  #[Test]
  public function testUploadRejectsANonStringInterventionField(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Multipart field "intervention" must be a intervention IRI.');

    $this->processor(requestStack: $this->uploadRequest(['intervention' => 42]))->process(null, new Post());
  }

  #[Test]
  public function testUploadRejectsANonStringClientId(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Multipart field "clientId" must be a UUID.');

    $this->processor(requestStack: $this->uploadRequest(['clientId' => 42]))->process(null, new Post());
  }

  #[Test]
  public function testUploadRejectsAMalformedClientId(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Multipart field "clientId" must be a UUID.');

    $this->processor(requestStack: $this->uploadRequest(['clientId' => 'not-a-uuid']))->process(null, new Post());
  }

  /**
   * `AttachmentConstraints::MAX_SIZE_BYTES` is 10 MiB. A file one byte over
   * must be rejected by `MultipartAttachmentGuard`'s size check BEFORE the
   * clientId-dedup short-circuit's own lookup is reached for a fresh upload,
   * and BEFORE persistence — asserted here by the command bus never being
   * dispatched. See `FacilityMediaProcessorTest`'s identical case: this
   * environment's php.ini caps `upload_max_filesize` well under 10 MiB, so a
   * real oversized multipart upload never reaches the guard through the HTTP
   * layer and this boundary is exercised as a unit test instead.
   */
  #[Test]
  public function testUploadRejectsAFileJustOverTheMaxSizeBeforeDispatch(): void
  {
    $equipment = $this->equipment();

    $oversizedFile = new class ($this->uploadPath(), 'photo.jpg', 'image/jpeg', null, true) extends UploadedFile {
      public function getSize(): int
      {
        return AttachmentConstraints::MAX_SIZE_BYTES + 1;
      }
    };

    $requestStack = new RequestStack();
    $requestStack->push(Request::create(
      '/api/media',
      'POST',
      ['equipment' => '/api/equipment/' . self::EQUIPMENT_ID],
      [],
      ['file' => $oversizedFile],
    ));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $requestStack,
      commandBus: $commandBus,
    )->process(null, new Post());
  }

  #[Test]
  public function testUploadRejectsADisallowedMimeTypeBeforeDispatch(): void
  {
    $equipment = $this->equipment();

    $path = tempnam(sys_get_temp_dir(), 'media-exe-');
    self::assertIsString($path);
    file_put_contents($path, 'MZ');

    try {
      $requestStack = new RequestStack();
      $requestStack->push(Request::create(
        '/api/media',
        'POST',
        ['equipment' => '/api/equipment/' . self::EQUIPMENT_ID],
        [],
        ['file' => new UploadedFile($path, 'payload.exe', 'application/x-msdownload', null, true)],
      ));

      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::never())->method('dispatch');

      $this->expectException(UnprocessableEntityHttpException::class);

      $this->processor(
        entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
        requestStack: $requestStack,
        commandBus: $commandBus,
      )->process(null, new Post());
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testUploadReportsAnUnknownEquipmentAsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Equipment not found.');

    $this->processor(
      entityManager: $this->entityManager([]),
      requestStack: $this->uploadRequest(),
    )->process(null, new Post());
  }

  #[Test]
  public function testUploadReplaysAnAlreadyStoredClientId(): void
  {
    $equipment = $this->equipment();
    $existing = $this->attachment($equipment);

    $result = $this->processor(
      entityManager: $this->entityManager([
        EquipmentRecord::class => $equipment,
        EquipmentAttachmentRecord::class => $existing,
      ]),
      requestStack: $this->uploadRequest(['clientId' => self::CLIENT_ID]),
    )->process(null, new Post());

    self::assertSame($existing->id, $result?->id);
  }

  #[Test]
  public function testUploadRejectsAClientIdOwnedByAnotherEquipment(): void
  {
    $equipment = $this->equipment();
    $other = $this->equipment();
    $other->id = '550e8400-e29b-41d4-a716-4466554400ff';
    $existing = $this->attachment($other);

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Media client UUID is already assigned to another equipment.');

    $this->processor(
      entityManager: $this->entityManager([
        EquipmentRecord::class => $equipment,
        EquipmentAttachmentRecord::class => $existing,
      ]),
      requestStack: $this->uploadRequest(['clientId' => self::CLIENT_ID]),
    )->process(null, new Post());
  }

  #[Test]
  public function testUploadReportsAVanishedStoredMediaAsNotFound(): void
  {
    $equipment = $this->equipment();

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new AddAttachmentResult(
      'attachment-id',
      self::EQUIPMENT_ID,
      'photo.jpg',
      'image/jpeg',
      5,
      null,
      new DateTimeImmutable(),
    ));

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Uploaded media not found.');

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $this->uploadRequest(),
      commandBus: $commandBus,
    )->process(null, new Post());
  }

  #[Test]
  public function testUploadOnADraftEquipmentInheritsItsInterventionScope(): void
  {
    $equipment = $this->equipment();
    $equipment->recordStatus = 'draft';
    $equipment->interventionId = self::INTERVENTION_ID;
    $attachment = $this->attachment($equipment);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new AddAttachmentResult(
      $attachment->id,
      self::EQUIPMENT_ID,
      'photo.jpg',
      'image/jpeg',
      5,
      null,
      new DateTimeImmutable(),
    ));

    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $context = new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft');
    $resources->method('interventionAssignmentContext')->willReturn($context);
    $resources->method('interventionMutationContext')->willReturn($context);
    $resources->expects(self::once())->method('touchDraftIntervention')->with(self::INTERVENTION_ID);

    $result = $this->processor(
      entityManager: $this->entityManager([
        EquipmentRecord::class => $equipment,
        EquipmentAttachmentRecord::class => $attachment,
      ]),
      requestStack: $this->uploadRequest(),
      resources: $resources,
      commandBus: $commandBus,
    )->process(null, new Post());

    self::assertSame($attachment->id, $result?->id);
  }

  #[Test]
  public function testDeleteReportsANonStringIdentifierAsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Media not found.');

    $this->processor(requestStack: $this->deleteRequest())->process(null, new Delete(), ['id' => 42]);
  }

  #[Test]
  public function testDeleteReportsAnUnknownMediaAsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->processor(
      entityManager: $this->entityManager([]),
      requestStack: $this->deleteRequest(),
    )->process(null, new Delete(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testDeleteReportsAnOrphanedEquipmentAsNotFound(): void
  {
    $equipment = $this->equipment();
    $equipment->organization = null;
    $attachment = $this->attachment($equipment);

    $this->expectException(NotFoundHttpException::class);

    $this->processor(
      entityManager: $this->entityManager([EquipmentAttachmentRecord::class => $attachment]),
      requestStack: $this->deleteRequest(),
    )->process(null, new Delete(), ['id' => $attachment->id]);
  }

  #[Test]
  public function testDeleteTouchesTheDraftInterventionOfAScratchpadEquipment(): void
  {
    $equipment = $this->equipment();
    $equipment->recordStatus = 'draft';
    $equipment->interventionId = self::INTERVENTION_ID;
    $attachment = $this->attachment($equipment);

    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $context = new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft');
    $resources->method('interventionAssignmentContext')->willReturn($context);
    $resources->method('interventionMutationContext')->willReturn($context);
    $resources->expects(self::once())->method('touchDraftIntervention')->with(self::INTERVENTION_ID);

    $result = $this->processor(
      entityManager: $this->entityManager([EquipmentAttachmentRecord::class => $attachment]),
      requestStack: $this->deleteRequest(),
      resources: $resources,
    )->process(null, new Delete(), ['id' => $attachment->id]);

    self::assertNull($result);
  }

  #[Test]
  public function testAssertWriteRequiresAnAuthenticatedSecurityUser(): void
  {
    $equipment = $this->equipment();

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $this->uploadRequest(),
      authenticated: false,
    )->process(null, new Post());
  }

  #[Test]
  public function testAssertWriteRejectsACrossOrganizationIntervention(): void
  {
    $equipment = $this->equipment();

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(null);

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Intervention and equipment must belong to the same organization.');

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $this->uploadRequest(['intervention' => '/api/interventions/' . self::INTERVENTION_ID]),
      resources: $resources,
    )->process(null, new Post());
  }

  #[Test]
  public function testAssertWriteRejectsAnEquipmentOutsideTheInterventionScope(): void
  {
    $equipment = $this->equipment();

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );
    $resources->method('resourceInInterventionScope')->willReturn(false);

    $this->expectException(ConflictHttpException::class);
    $this->expectExceptionMessage('Equipment is outside the intervention scope.');

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $this->uploadRequest(['intervention' => '/api/interventions/' . self::INTERVENTION_ID]),
      resources: $resources,
    )->process(null, new Post());
  }

  #[Test]
  public function testAssertWriteReportsAnUnknownInterventionAsNotFound(): void
  {
    $equipment = $this->equipment();

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionAssignmentContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );
    $resources->method('resourceInInterventionScope')->willReturn(true);
    $resources->method('interventionMutationContext')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $this->uploadRequest(['intervention' => '/api/interventions/' . self::INTERVENTION_ID]),
      resources: $resources,
    )->process(null, new Post());
  }

  #[Test]
  public function testAssertWriteRejectsANonParticipatingMember(): void
  {
    $equipment = $this->equipment();

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $context = new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress');
    $resources->method('interventionAssignmentContext')->willReturn($context);
    $resources->method('resourceInInterventionScope')->willReturn(true);
    $resources->method('interventionMutationContext')->willReturn($context);

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationAndUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Only the assigned member can execute this work item.');

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $this->uploadRequest(['intervention' => '/api/interventions/' . self::INTERVENTION_ID]),
      resources: $resources,
      memberPolicy: new InterventionMemberPolicy($members),
    )->process(null, new Post());
  }

  #[Test]
  public function testAssertWriteRejectsACallerWithoutTheRequiredPermission(): void
  {
    $equipment = $this->equipment();

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.equipment.write permission.');

    $this->processor(
      entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
      requestStack: $this->uploadRequest(),
      decision: OrganizationAccessDecision::MISSING_PERMISSION,
    )->process(null, new Post());
  }

  #[Test]
  public function testAssertWriteThrowsNotFoundWhenOrganizationIsOutsideCallersScope(): void
  {
    $equipment = $this->equipment();

    // Not AccessDeniedHttpException: a 403 for a caller outside the organization's
    // scope would confirm the record exists across an organization boundary.
    try {
      $this->processor(
        entityManager: $this->entityManager([EquipmentRecord::class => $equipment]),
        requestStack: $this->uploadRequest(),
        decision: OrganizationAccessDecision::OUTSIDE_SCOPE,
      )->process(null, new Post());
      self::fail('Expected NotFoundHttpException to be thrown.');
    } catch (NotFoundHttpException $exception) {
      self::assertSame('Equipment not found.', $exception->getMessage());
    }
  }

  private function processor(
    ?EntityManagerInterface $entityManager = null,
    ?RequestStack $requestStack = null,
    ?InterventionResourceGatewayPort $resources = null,
    ?CommandBusPort $commandBus = null,
    OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED,
    bool $authenticated = true,
    ?InterventionMemberPolicy $memberPolicy = null,
  ): MediaProcessor {
    $entityManager ??= $this->entityManager([]);
    $requestStack ??= $this->uploadRequest();
    $resources ??= $this->createStub(InterventionResourceGatewayPort::class);
    $commandBus ??= $this->createStub(CommandBusPort::class);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      $authenticated ? new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true) : null,
    );

    return new MediaProcessor(
      $entityManager,
      $commandBus,
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($resources, $memberPolicy),
      new RevisionGuard($requestStack),
      new MultipartAttachmentGuard(),
    );
  }

  /**
   * @param array<class-string, object> $records
   */
  private function entityManager(array $records): EntityManagerInterface
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturnCallback(
      static fn (string $class): ?object => $records[$class] ?? null,
    );

    return $entityManager;
  }

  /**
   * @param array<string, mixed> $parameters
   */
  private function uploadRequest(array $parameters = [], bool $withFile = true): RequestStack
  {
    $files = [];

    if ($withFile) {
      $parameters['equipment'] ??= '/api/equipment/' . self::EQUIPMENT_ID;
      $files['file'] = new UploadedFile($this->uploadPath(), 'photo.jpg', 'image/jpeg', null, true);
    }

    $stack = new RequestStack();
    $stack->push(Request::create('/api/media', 'POST', $parameters, [], $files));

    return $stack;
  }

  private function deleteRequest(): RequestStack
  {
    $request = Request::create('/api/media/attachment-id', 'DELETE');
    $request->headers->set('If-Match', '"revision-1"');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function uploadPath(): string
  {
    if (null === $this->uploadPath) {
      $path = tempnam(sys_get_temp_dir(), 'media-');
      self::assertIsString($path);
      file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));
      $this->uploadPath = $path;
    }

    return $this->uploadPath;
  }

  private function equipment(): EquipmentRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->recordStatus = 'published';

    return $equipment;
  }

  private function attachment(EquipmentRecord $equipment): EquipmentAttachmentRecord
  {
    $attachment = new EquipmentAttachmentRecord();
    $attachment->id = self::CLIENT_ID;
    $attachment->equipment = $equipment;
    $attachment->fileName = 'photo.jpg';
    $attachment->mimeType = 'image/jpeg';
    $attachment->size = 5;
    $attachment->uploadedAt = new DateTimeImmutable();

    return $attachment;
  }
}
