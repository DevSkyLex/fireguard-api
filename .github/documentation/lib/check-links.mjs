import { readFile, stat, realpath } from 'node:fs/promises';
import path from 'node:path';
import { parseMarkdown } from './parse-markdown.mjs';

export async function checkLinks(root, documents) {
  const errors = [];
  const parsed = new Map();
  const loading = new Map();
  const rootPath = await realpath(root);
  const withinRoot = (file) => {
    const relative = path.relative(rootPath, file);
    return relative !== '..' && !relative.startsWith(`..${path.sep}`) && !path.isAbsolute(relative);
  };
  async function load(file) {
    if (!loading.has(file)) {
      loading.set(
        file,
        readFile(file, 'utf8').then((source) => {
          const document = parseMarkdown(source);
          parsed.set(file, document);
          return document;
        }),
      );
    }
    return loading.get(file);
  }
  let checked = 0;
  await Promise.all(
    documents.map(async (relative) => {
      const sourceFile = path.resolve(rootPath, relative);
      const document = await load(sourceFile);
      await Promise.all(
        document.links.map(async ({ target, line }) => {
          if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(target)) return;
          checked += 1;
          try {
            const [filePart, fragment] = target.split('#', 2);
            const decoded = decodeURIComponent(filePart.split('?')[0]);
            let destination = decoded
              ? path.resolve(
                  decoded.startsWith('/') ? rootPath : path.dirname(sourceFile),
                  decoded.replace(/^\//, ''),
                )
              : sourceFile;
            if (!withinRoot(destination))
              throw new Error('local target leaves the repository; use its GitHub URL');
            const metadata = await stat(destination);
            destination = await realpath(destination);
            if (!withinRoot(destination)) throw new Error('symlink target leaves the repository');
            if (!fragment) return;
            if (metadata.isDirectory()) destination = path.join(destination, 'README.md');
            if (/\.md$/i.test(destination)) {
              const anchor = decodeURIComponent(fragment);
              if (!(await load(destination)).anchors.has(anchor))
                throw new Error(`unknown anchor #${anchor}`);
            } else if (!/^L\d+(?:-L\d+)?$/.test(fragment)) {
              throw new Error('fragment on a non-Markdown target must be a GitHub line anchor');
            }
          } catch (error) {
            errors.push(
              `${relative}:${line}: ${target}: ${error.code === 'ENOENT' ? 'missing local target' : error.message}`,
            );
          }
        }),
      );
    }),
  );
  return { errors: errors.toSorted(), parsed, checked };
}
