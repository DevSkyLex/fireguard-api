<?php

declare(strict_types=1);

namespace Tests\Support\Factory;

use Intervention\Presentation\Api\Factory\InterventionWorkItemOutputFactory;

/** @phpstan-require-extends \PHPUnit\Framework\TestCase */
trait WorkItemOutputFactoryTrait
{
  private function workItemOutputFactory(): InterventionWorkItemOutputFactory
  {
    return new InterventionWorkItemOutputFactory();
  }
}
