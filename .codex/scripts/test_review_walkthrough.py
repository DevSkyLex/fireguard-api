"""Walk through the review skill's Git states with a masked staged regression."""
from pathlib import Path
import subprocess
import tempfile
import unittest


class ReviewWalkthroughTests(unittest.TestCase):
    def test_index_regression_and_untracked_source_survive_head_diff_cancellation(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)

            def git(*arguments):
                return subprocess.run(['git', *arguments], cwd=root, check=True, capture_output=True,
                                      text=True, encoding='utf-8', timeout=10).stdout

            git('init', '--quiet')
            path = root / 'access.py'
            baseline = 'def permits(tenant):\n    return tenant is not None\n'
            path.write_text(baseline, encoding='utf-8')
            git('add', '--', 'access.py')
            git('-c', 'user.name=Tooling fixture', '-c', 'user.email=fixture@example.invalid',
                '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'Baseline')
            path.write_text('def permits(tenant):\n    return True\n', encoding='utf-8')
            git('add', '--', 'access.py')
            path.write_text(baseline, encoding='utf-8')
            (root / 'new_access.py').write_text('def permits(tenant):\n    return True\n', encoding='utf-8')

            self.assertEqual(git('diff', 'HEAD', '--', 'access.py'), '')
            self.assertIn('MM access.py', git('status', '--short'))
            self.assertIn('access.py', git('diff', '--cached', '--name-status'))
            self.assertIn('access.py', git('diff', '--name-status'))
            self.assertIn('+    return True', git('diff', '--cached', '--', 'access.py'))
            self.assertIn('-    return True', git('diff', '--', 'access.py'))
            self.assertEqual(git('ls-files', '--others', '--exclude-standard').strip(), 'new_access.py')
            self.assertIn('return True', (root / 'new_access.py').read_text(encoding='utf-8'))
