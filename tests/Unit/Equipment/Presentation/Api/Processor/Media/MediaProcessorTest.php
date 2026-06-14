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
use Mission\Application\Contract\Resource\MissionAssignmentContext;
use Mission\Application\Port\Outbound\MissionResourceGatewayPort;
use Mission\Application\Service\MissionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(MediaProcessor::class)]
final class MediaProcessorTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string MISSION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testDeletingMediaFromPublishedEquipmentUsesOperationalPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->missionId = self::MISSION_ID;
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
    $resources = $this->createMock(MissionResourceGatewayPort::class);
    $resources->expects(self::never())->method('missionMutationContext');
    $resources->expects(self::never())->method('touchDraftMission');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-id', self::ORGANIZATION_ID, 'organization.equipment.write')
      ->willReturn(true);
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
      new MissionResourceManager($resources),
      new RevisionGuard($requestStack),
    )->process(null, new Delete(), ['id' => $attachment->id]);

    self::assertNull($result);
  }

  #[Test]
  public function testMissionContextAuthorizesEvidenceForPublishedEquipment(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'media-');
    self::assertIsString($path);
    file_put_contents($path, 'photo');

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
      $resources = $this->createMock(MissionResourceGatewayPort::class);
      $context = new MissionAssignmentContext(
        self::MISSION_ID,
        self::ORGANIZATION_ID,
        'in_progress',
      );
      $resources->method('missionAssignmentContext')->willReturn($context);
      $resources->method('missionMutationContext')->willReturn($context);
      $resources->method('resourceInMissionScope')->willReturn(true);
      $resources->expects(self::once())->method('touchDraftMission')->with(self::MISSION_ID);
      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('hasPermission')->willReturn(true);
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
          'mission' => '/api/missions/' . self::MISSION_ID,
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
        new MissionResourceManager($resources),
        new RevisionGuard($requestStack),
      )->process(null, new Post());

      self::assertSame($attachment->id, $result?->id);
    } finally {
      unlink($path);
    }
  }
}
