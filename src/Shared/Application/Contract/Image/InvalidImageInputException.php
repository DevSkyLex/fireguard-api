<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Image;

use InvalidArgumentException;

/**
 * Class InvalidImageInputException
 *
 * Rejects uploaded image content that cannot be processed within the shared
 * input budget. The HTTP boundary maps this failure to validation status 422.
 *
 * @category Contract Exception
 */
final class InvalidImageInputException extends InvalidArgumentException
{
}
