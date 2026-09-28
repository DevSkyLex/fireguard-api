import { execFile } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const execute = promisify(execFile);
const cli = fileURLToPath(
  new URL('../node_modules/@mermaid-js/mermaid-cli/src/cli.js', import.meta.url),
);

export async function renderMermaid(diagrams, outputDirectory) {
  await mkdir(outputDirectory, { recursive: true });
  const config = path.join(outputDirectory, 'mermaid-config.json');
  const browserConfig = path.join(outputDirectory, 'browser-config.json');
  await writeFile(config, JSON.stringify({ securityLevel: 'strict', theme: 'neutral' }));
  // Hosted Linux runners have no user namespace sandbox. Inputs are repository diagrams;
  // this isolated job receives no application or deployment secrets.
  await writeFile(
    browserConfig,
    JSON.stringify({ args: process.platform === 'linux' ? ['--no-sandbox'] : [] }),
  );
  const errors = [];
  let rendered = 0;
  // Serialize browser processes to keep memory bounded even for large document sets.
  await diagrams.reduce(
    (completed, diagram, index) =>
      completed.then(async () => {
        const basename = `${String(index + 1).padStart(3, '0')}-${diagram.file.replaceAll(/[^a-zA-Z0-9._-]/g, '-')}-${diagram.line}`;
        const input = path.join(outputDirectory, `${basename}.mmd`);
        await writeFile(input, diagram.source);
        try {
          if (!diagram.source.trim()) throw new Error('empty Mermaid block');
          await execute(
            process.execPath,
            [
              cli,
              '-i',
              input,
              '-o',
              path.join(outputDirectory, `${basename}.svg`),
              '-c',
              config,
              '-p',
              browserConfig,
              '-b',
              'transparent',
            ],
            { timeout: 60000, maxBuffer: 1024 * 1024 },
          );
          rendered += 1;
        } catch (error) {
          const diagnostic = (error.stderr || error.message).trim().slice(0, 1600);
          errors.push(`${diagram.file}:${diagram.line}: Mermaid: ${diagnostic}`);
        }
      }),
    Promise.resolve(),
  );
  await writeFile(
    path.join(outputDirectory, 'manifest.json'),
    JSON.stringify({ rendered, diagrams, errors }, null, 2),
  );
  return { rendered, errors };
}
