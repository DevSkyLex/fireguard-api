from pathlib import Path
import os
import re
import shutil
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]
ACCEPTED = ('docs/git-naming', 'docs/conventions-2', 'feat/new-module', 'fix/session-rotation',
            'chore/tooling', 'refactor/use-cases', 'test/git-hooks', 'perf/repository-queries',
            'ci/naming', 'build/dependencies', 'style/formatting', 'revert/session-change',
            'hotfix/token-expiry')
REJECTED = ('codex/', 'codex/ui-cleanup', 'feat/Uppercase', 'feat/has_underscore', 'feat/-leading',
            'feat/trailing-', 'feat/double--dash', 'feat/nested/path', 'unknown/change')


class GitConventionsTests(unittest.TestCase):
    def test_ci_requires_semantic_branch_prefix_and_kebab_description(self):
        source = (ROOT / '.github/workflows/conventions.yml').read_text(encoding='utf-8')
        pattern = re.search(r"pattern='([^']+)'", source).group(1)
        for branch in (*ACCEPTED, *REJECTED):
            with self.subTest(branch=branch):
                self.assertEqual(re.fullmatch(pattern, branch) is not None, branch in ACCEPTED)

    def test_actual_pre_push_hook_requires_semantic_branch_names(self):
        shell = shutil.which('sh')
        environment = dict(os.environ)
        if not shell and (git := shutil.which('git')):
            candidate = Path(git).resolve().parent.parent / 'bin/sh.exe'
            shell = str(candidate) if candidate.is_file() else None
            if shell:
                # Git for Windows supplies grep alongside its POSIX utilities.
                tools = candidate.parent.parent / 'usr/bin'
                environment['PATH'] = str(tools) + os.pathsep + environment.get('PATH', '')
        if not shell:
            self.skipTest('A POSIX shell is required to execute the Git pre-push hook.')
        # No vendor tree in this fixture: execute the unmodified hook's ref checks
        # without running PHP gates, pushing anything or contacting a remote.
        with tempfile.TemporaryDirectory(prefix='fg-api-hook-test-') as directory:
            for branch in (*ACCEPTED, *REJECTED):
                with self.subTest(branch=branch):
                    result = subprocess.run(
                        [shell, (ROOT / '.githooks/pre-push').as_posix()], cwd=directory, env=environment,
                        input=f'refs/heads/{branch} {"a" * 40} refs/heads/{branch} {"0" * 40}\n',
                        text=True, capture_output=True, timeout=10,
                    )
                    self.assertEqual(result.returncode, 0 if branch in ACCEPTED else 1,
                                     result.stdout + result.stderr)


if __name__ == '__main__':
    unittest.main()
