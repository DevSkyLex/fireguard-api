<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query;

use Intervention\Application\Contract\Label\InterventionLabelPage;
use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\Contract\Template\InterventionTemplatePage;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowPage, InterventionWorkflowView};
use Intervention\Application\UseCase\Query\Activity\ListInterventionActivities\ListInterventionActivitiesResult;
use Intervention\Application\UseCase\Query\Label\ListInterventionLabels\ListInterventionLabelsResult;
use Intervention\Application\UseCase\Query\Template\ListInterventionTemplates\ListInterventionTemplatesResult;
use Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow\GetInterventionWorkflowResult;
use Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues\ListInterventionIssuesResult;
use Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\ListInterventionWorkflowResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test InterventionQueryResultsTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListInterventionActivitiesResult::class)]
#[CoversClass(ListInterventionLabelsResult::class)]
#[CoversClass(ListInterventionTemplatesResult::class)]
#[CoversClass(GetInterventionWorkflowResult::class)]
#[CoversClass(ListInterventionIssuesResult::class)]
#[CoversClass(ListInterventionWorkflowResult::class)]
final class InterventionQueryResultsTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testListInterventionActivitiesResultCarriesTheWorkflowPage(): void
  {
    $page = new InterventionWorkflowPage([], 1, 30, 0);
    $result = new ListInterventionActivitiesResult($page);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($page, $result->page);
  }

  #[Test]
  public function testListInterventionWorkflowResultCarriesTheWorkflowPage(): void
  {
    $view = new InterventionWorkflowView('work_item', '550e8400-e29b-41d4-a716-446655440002', ['id' => 'a']);
    $page = new InterventionWorkflowPage([$view], 2, 10, 11);
    $result = new ListInterventionWorkflowResult($page);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($page, $result->page);
    self::assertSame(2, $result->page->page);
    self::assertSame(11, $result->page->total);
  }

  #[Test]
  public function testListInterventionLabelsResultCarriesTheLabelPage(): void
  {
    $page = new InterventionLabelPage([], 1, 30, 0);
    $result = new ListInterventionLabelsResult($page);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($page, $result->page);
  }

  #[Test]
  public function testListInterventionTemplatesResultCarriesTheTemplatePage(): void
  {
    $page = new InterventionTemplatePage([], 1, 30, 0);
    $result = new ListInterventionTemplatesResult($page);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($page, $result->page);
  }

  #[Test]
  public function testGetInterventionWorkflowResultCarriesTheView(): void
  {
    $view = new InterventionWorkflowView('intervention', '550e8400-e29b-41d4-a716-446655440002', ['id' => 'x']);
    $result = new GetInterventionWorkflowResult($view);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame($view, $result->view);
  }

  #[Test]
  public function testListInterventionIssuesResultCarriesTheIssues(): void
  {
    $issue = new InterventionIssue('blocker', 'facility', '550e8400-e29b-41d4-a716-446655440004', 'name', 'Missing name.');
    $result = new ListInterventionIssuesResult([$issue]);

    self::assertInstanceOf(ResultMessage::class, $result);
    self::assertSame([$issue], $result->issues);
  }
  // #endregion
}
