<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\EventSubscriber;

use Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict;
use Intervention\Presentation\Api\EventSubscriber\InterventionDraftDependencySubscriber;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(InterventionDraftDependencySubscriber::class)]
final class InterventionDraftDependencySubscriberTest extends TestCase
{
  #[Test]
  public function itReportsActionableCountsWithoutExposingEquipmentIdentifiers(): void
  {
    $error = new InterventionDraftDependencyConflict([
      ['resourceType' => 'equipment', 'resourceId' => 'protected-equipment', 'relatedResourceId' => 'draft-facility'],
      ['resourceType' => 'facility', 'resourceId' => 'published-child', 'relatedResourceId' => 'draft-facility'],
    ]);
    $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/interventions/id'), HttpKernelInterface::MAIN_REQUEST, new RuntimeException('wrapped', previous: $error));
    new InterventionDraftDependencySubscriber()->onException($event);
    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame(409, $response->getStatusCode());
    $body = (string) $response->getContent();
    self::assertStringContainsString('intervention_draft_dependencies', $body);
    self::assertStringContainsString('"equipment":1', $body);
    self::assertStringNotContainsString('protected-equipment', $body);
    self::assertStringNotContainsString('draft-facility', $body);
  }
}
