<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionOutputFactory::class)]
final class InterventionOutputFactoryTest extends TestCase
{
  #[Test]
  public function itMapsEmbeddedLabelSummaries(): void
  {
    $view = new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([
      ['id' => 'label-1', 'name' => 'Urgent', 'color' => '#ff0000'],
      ['id' => 'label-2', 'name' => 'Follow-up', 'color' => '#00ff00'],
    ]));

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())->fromView($view);

    self::assertSame(
      [
        ['id' => 'label-1', 'name' => 'Urgent', 'color' => '#ff0000'],
        ['id' => 'label-2', 'name' => 'Follow-up', 'color' => '#00ff00'],
      ],
      $output->labels,
    );
  }

  #[Test]
  public function itMapsAnEmptyLabelListWhenNoLabelsAreAssigned(): void
  {
    $view = new InterventionWorkflowView('intervention', 'organization-1', $this->baseData([]));

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())->fromView($view);

    self::assertSame([], $output->labels);
  }

  #[Test]
  public function itOffersNoTransitionsWhenTheStoredStatusIsNotAKnownWorkflowStatus(): void
  {
    $data = $this->baseData([]);
    $data['status'] = 'archived_by_hand';

    $output = new InterventionOutputFactory(new InterventionTransitionPolicy())
      ->fromView(new InterventionWorkflowView('intervention', 'organization-1', $data));

    self::assertSame([], $output->allowedTransitions);
  }

  /**
   * @param list<array{id: string, name: string, color: string}> $labels
   *
   * @return array<string, mixed>
   */
  private function baseData(array $labels): array
  {
    return [
      'id' => 'intervention-1',
      'organization' => '/api/organizations/organization-1',
      'number' => 1,
      'type' => 'site_setup',
      'name' => 'Mission',
      'description' => null,
      'status' => 'draft',
      'site' => null,
      'responsible' => null,
      'participants' => [],
      'priority' => 'normal',
      'plannedStartAt' => null,
      'dueAt' => null,
      'reviewNote' => null,
      'revision' => 1,
      'facilitiesCount' => 0,
      'equipmentCount' => 0,
      'inspectionsCount' => 0,
      'blockersCount' => 0,
      'workItemsCount' => 0,
      'completedWorkItemsCount' => 0,
      'proposedChangesCount' => 0,
      'commentsCount' => 0,
      'labels' => $labels,
      'createdAt' => '2026-07-09T13:00:00+00:00',
      'updatedAt' => '2026-07-09T13:00:00+00:00',
    ];
  }
}
