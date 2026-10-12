<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Validator\ConfiguredPassword;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ConfiguredPassword.
 *
 * @category Validator
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ConfiguredPassword extends Constraint
{
}
