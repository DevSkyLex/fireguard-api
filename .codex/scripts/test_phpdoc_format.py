"""Regression checks for the requested PHPDoc layout through the actual fixer profiles."""
from pathlib import Path
import json
import shutil
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')
FIXER = ROOT / 'vendor/bin/php-cs-fixer'
EXPECTED = r"""<?php

declare(strict_types=1);

namespace Notification\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * Class NotificationId
 *
 * Identifies a notification using the shared UUID representation.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationId extends Uuid
{
  // #region Methods
  /**
   * Method fromString
   *
   * Creates a notification identifier from its UUID representation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value the UUID representation
   *
   * @return self the notification identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
"""


@unittest.skipUnless(PHP and FIXER.is_file(), 'PHP and the installed project fixer are required.')
class PhpdocFormatTests(unittest.TestCase):
    def format_source(self, source, profile):
        with tempfile.TemporaryDirectory(prefix='fg-phpdoc-') as directory:
            fixture = Path(directory) / 'NotificationId.php'
            fixture.write_text(source, encoding='utf-8', newline='\n')
            result = subprocess.run(
                [PHP, '-d', 'memory_limit=1G', str(FIXER), 'fix',
                 '--config=' + str(ROOT / profile), '--using-cache=no',
                 '--path-mode=override', '--sequential', '--', str(fixture)],
                cwd=ROOT, capture_output=True, text=True, timeout=30,
            )
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            return fixture.read_text(encoding='utf-8')

    def tokens(self, source):
        code = (
            '$result = []; foreach (token_get_all(stream_get_contents(STDIN)) as $token) {'
            'if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) continue;'
            '$result[] = is_array($token) ? [$token[0], $token[1]] : $token; }'
            'echo json_encode($result, JSON_THROW_ON_ERROR);'
        )
        result = subprocess.run([PHP, '-r', code], input=source, capture_output=True,
                                text=True, timeout=10)
        self.assertEqual(result.returncode, 0, result.stderr)
        return json.loads(result.stdout)

    def test_requested_example_is_stable_in_both_profiles(self):
        for profile in ('.php-cs-fixer.comments.php', '.php-cs-fixer.dist.php'):
            with self.subTest(profile=profile):
                output = self.format_source(EXPECTED, profile)
                self.assertEqual(output, EXPECTED)
                self.assertEqual(self.format_source(output, profile), output)

    def test_comment_profile_groups_metadata_without_changing_php_tokens(self):
        source = EXPECTED.replace(
            ' * @category ValueObject\n * @version 1.0.0',
            ' * @version 1.0.0\n *\n * @category ValueObject',
        ).replace('   * @access public\n   * @since 1.0.0', '   * @since 1.0.0').replace(
            '   * @return self the notification identifier',
            '   * @return self the notification identifier\n   *\n   * @access public',
        )
        output = self.format_source(source, '.php-cs-fixer.comments.php')
        self.assertEqual(output, EXPECTED)
        self.assertEqual(self.tokens(output), self.tokens(source))


if __name__ == '__main__':
    unittest.main()
