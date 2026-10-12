<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Exception;

use RuntimeException;

/**
 * Class FacilityCsvRenderingException
 *
 * Signals a facility CSV stream failure before its artifact and receipt can commit.
 *
 * @category Exception
 */
final class FacilityCsvRenderingException extends RuntimeException
{
}
