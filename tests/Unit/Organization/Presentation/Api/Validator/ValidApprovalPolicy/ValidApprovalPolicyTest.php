<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Validator\ValidApprovalPolicy;

use Organization\Presentation\Api\Validator\ValidApprovalPolicy\ValidApprovalPolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;

/**
 * Test ValidApprovalPolicy.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ValidApprovalPolicy::class)]
final class ValidApprovalPolicyTest extends TestCase
{
  #[Test]
  public function testTargetsTheWholeClass(): void
  {
    self::assertSame(Constraint::CLASS_CONSTRAINT, new ValidApprovalPolicy()->getTargets());
  }

  #[Test]
  public function testCarriesADefaultUnknownActionTypeMessage(): void
  {
    $constraint = new ValidApprovalPolicy();

    self::assertStringContainsString('{{ value }}', $constraint->unknownActionTypeMessage);
    self::assertStringContainsString('{{ types }}', $constraint->unknownActionTypeMessage);
  }
}
