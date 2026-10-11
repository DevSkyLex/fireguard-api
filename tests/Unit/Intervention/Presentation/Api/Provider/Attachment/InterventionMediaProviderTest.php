<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Attachment\InterventionAttachmentView;
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\ListInterventionAttachmentsResult;
use Intervention\Application\UseCase\Query\Attachment\ResolveInterventionMedia\{ResolveInterventionMediaQuery, ResolveInterventionMediaResult};
use Intervention\Presentation\Api\Provider\Attachment\InterventionMediaProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class InterventionMediaProviderTest extends TestCase
{
  #[Test]
  public function translatesTheStoredApplicationProjection(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $view = new InterventionAttachmentView('attachment', 'organization', 'intervention', 'file.pdf', 'application/pdf', 4, 'proof', 3, '2026-01-01T00:00:00+00:00', null, 'file');
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ResolveInterventionMediaQuery $query): bool => 'actor' === $query->userId && 'attachment' === $query->attachmentId))->willReturn(new ResolveInterventionMediaResult('organization', 'intervention', $view));
    $output = $this->provider($bus)->provide(new Get(), ['id' => 'attachment']);
    self::assertInstanceOf(\Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput::class, $output);
    self::assertSame('attachment', $output->id);
    self::assertSame(3, $output->revision);
    self::assertSame('proof', $output->label);
  }

  #[Test]
  public function translatesAnAuthorizedCollection(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->willReturnCallback(static function (object $query): object {
      return new ListInterventionAttachmentsResult([['id' => 'attachment', 'fileName' => 'file.pdf', 'mimeType' => 'application/pdf', 'size' => 4, 'label' => null, 'uploadedAt' => '2026-01-01T00:00:00+00:00', 'workItemId' => null, 'kind' => 'file']]);
    });
    $outputs = $this->provider($bus)->provide(new GetCollection(), ['interventionId' => 'intervention']);
    self::assertIsArray($outputs);
    self::assertCount(1, $outputs);
    self::assertSame('attachment', $outputs[0]->id);
    self::assertSame('intervention', $outputs[0]->interventionId);
  }

  #[Test]
  public function refusesAnUnauthenticatedReadWithoutCallingTheBus(): void
  {
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $this->expectException(AccessDeniedHttpException::class);
    new InterventionMediaProvider($bus, $security, new RequestStack())->provide(new Get(), ['id' => 'attachment']);
  }

  private function provider(QueryBusPort $bus): InterventionMediaProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('actor', 'actor@example.com', '', [], [], true));

    return new InterventionMediaProvider($bus, $security, new RequestStack());
  }
}
