<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\Contract\Attachment\InspectionAttachmentView;
use Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments\ListInspectionAttachmentsResult;
use Inspection\Application\UseCase\Query\Attachment\ResolveInspectionMedia\{ResolveInspectionMediaQuery, ResolveInspectionMediaResult};
use Inspection\Presentation\Api\Provider\Attachment\InspectionMediaProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class InspectionMediaProviderTest extends TestCase
{
  #[Test]
  public function translatesTheStoredApplicationProjection(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $view = new InspectionAttachmentView('attachment', 'organization', 'inspection', 'file.pdf', 'application/pdf', 4, 'proof', 3, '2026-01-01T00:00:00+00:00', null);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ResolveInspectionMediaQuery $query): bool => 'actor' === $query->userId && 'attachment' === $query->attachmentId))->willReturn(new ResolveInspectionMediaResult('organization', 'inspection', $view));
    $output = $this->provider($bus)->provide(new Get(), ['id' => 'attachment']);
    self::assertInstanceOf(\Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput::class, $output);
    self::assertSame('attachment', $output->id);
    self::assertSame(3, $output->revision);
    self::assertSame('proof', $output->label);
  }

  #[Test]
  public function translatesAnAuthorizedCollection(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::exactly(2))->method('ask')->willReturnCallback(static function (object $query): object {
      if ($query instanceof ResolveInspectionMediaQuery) {
        return new ResolveInspectionMediaResult('organization', 'inspection');
      }

      return new ListInspectionAttachmentsResult([['id' => 'attachment', 'fileName' => 'file.pdf', 'mimeType' => 'application/pdf', 'size' => 4, 'label' => null, 'uploadedAt' => '2026-01-01T00:00:00+00:00', 'nonConformityId' => null]]);
    });
    $outputs = $this->provider($bus)->provide(new GetCollection(), ['inspectionId' => 'inspection']);
    self::assertIsArray($outputs);
    self::assertCount(1, $outputs);
    self::assertSame('attachment', $outputs[0]->id);
    self::assertSame('inspection', $outputs[0]->inspectionId);
  }

  #[Test]
  public function refusesAnUnauthenticatedReadWithoutCallingTheBus(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $this->expectException(AccessDeniedHttpException::class);
    new InspectionMediaProvider($bus, $security)->provide(new Get(), ['id' => 'attachment']);
  }

  private function provider(QueryBusPort $bus): InspectionMediaProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('actor', 'actor@example.com', '', [], [], true));

    return new InspectionMediaProvider($bus, $security);
  }
}
