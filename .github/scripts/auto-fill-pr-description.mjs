import { readFile } from 'node:fs/promises'
import process from 'node:process'

const MODEL_API_URL = 'https://models.github.ai/inference/chat/completions'
const MODEL_API_VERSION = '2022-11-28'

const token = process.env.GITHUB_TOKEN
const repository = process.env.GITHUB_REPOSITORY
const eventPath = process.env.GITHUB_EVENT_PATH
const apiUrl = process.env.GITHUB_API_URL ?? 'https://api.github.com'
const model = process.env.PR_AI_MODEL ?? 'openai/gpt-4.1'

if (!token || !repository || !eventPath) {
  console.log('Missing required GitHub Actions environment variables. Skipping PR autofill.')
  process.exit(0)
}

const [owner, repo] = repository.split('/')

if (!owner || !repo) {
  console.log(`Invalid GITHUB_REPOSITORY value: ${repository}`)
  process.exit(0)
}

const event = JSON.parse(await readFile(eventPath, 'utf8'))
const pullRequestEvent = event.pull_request

if (!pullRequestEvent) {
  console.log('No pull request payload found. Skipping PR autofill.')
  process.exit(0)
}

const pullRequest = await getPullRequest(owner, repo, pullRequestEvent.number) ?? pullRequestEvent
let body = typeof pullRequest.body === 'string' ? pullRequest.body : ''

if (body.includes('<!-- pr-ai:disable -->')) {
  console.log('PR AI autofill disabled in pull request body.')
  process.exit(0)
}

if ('' === body.trim()) {
  body = await readFile('.github/pull_request_template.md', 'utf8')
}

if (!body.includes('<!-- pr-ai:')) {
  console.log('No AI-managed markers found in PR body. Skipping PR autofill.')
  process.exit(0)
}

const files = await listPullRequestFiles(owner, repo, pullRequest.number)

if (0 === files.length) {
  console.log('Pull request has no changed files. Skipping PR autofill.')
  process.exit(0)
}

const promptPayload = buildPromptPayload({
  owner,
  repo,
  pullRequest,
  body,
  files,
})

const generated = await generateSections(promptPayload)

if (!generated) {
  process.exit(0)
}

const updatedBody = applyGeneratedSections(body, generated)

if (updatedBody === body) {
  console.log('PR body already contains manual content or no AI-managed block needed updates.')
  process.exit(0)
}

await updatePullRequest(owner, repo, pullRequest.number, updatedBody)
console.log('PR description updated successfully.')

async function listPullRequestFiles(ownerName, repoName, pullNumber) {
  const allFiles = []
  let page = 1

  while (true) {
    const filesPage = await githubRequest(
      'GET',
      `${apiUrl}/repos/${ownerName}/${repoName}/pulls/${pullNumber}/files?per_page=100&page=${page}`,
    )

    if (!Array.isArray(filesPage) || 0 === filesPage.length) {
      break
    }

    allFiles.push(...filesPage)

    if (filesPage.length < 100) {
      break
    }

    page += 1
  }

  return allFiles
}

async function getPullRequest(ownerName, repoName, pullNumber) {
  return githubRequest(
    'GET',
    `${apiUrl}/repos/${ownerName}/${repoName}/pulls/${pullNumber}`,
  )
}

function buildPromptPayload({ owner: ownerName, repo: repoName, pullRequest: pr, body: currentBody, files }) {
  const summarizedFiles = files.map((file) => {
    const status = typeof file.status === 'string' ? file.status : 'modified'
    const additions = Number.isFinite(file.additions) ? file.additions : 0
    const deletions = Number.isFinite(file.deletions) ? file.deletions : 0

    return `${status} ${file.filename} (+${additions}/-${deletions})`
  })

  const patchSnippets = files
    .filter((file) => 'string' === typeof file.patch && '' !== file.patch.trim())
    .slice(0, 15)
    .map((file) => {
      const patch = truncate(file.patch, 1400)

      return `FILE: ${file.filename}\n${patch}`
    })

  return {
    repository: `${ownerName}/${repoName}`,
    title: pr.title ?? '',
    draft: Boolean(pr.draft),
    additions: pr.additions ?? 0,
    deletions: pr.deletions ?? 0,
    changedFiles: pr.changed_files ?? files.length,
    currentBody,
    summarizedFiles: truncate(summarizedFiles.join('\n'), 12000),
    patchSnippets: truncate(patchSnippets.join('\n\n'), 18000),
  }
}

async function generateSections(payload) {
  const systemPrompt = [
    'You generate concise, factual pull request description content for a PHP/Symfony backend repository.',
    'Use only the provided pull request context.',
    'Do not invent tickets, tests, behavior changes, risks, rollback steps, or missing work.',
    'If a detail is unclear from the diff, return an empty array or "Needs manual confirmation."',
    'Keep bullet text short and actionable.',
    'Prefer module names inferred from paths such as Auth, OAuth, Organization, Onboarding, Facility, Equipment, Inspection, Shared, and .github.',
    'For main files or entry points, prefer concrete paths or named flows.',
  ].join(' ')

  const userPrompt = [
    `Repository: ${payload.repository}`,
    `PR title: ${payload.title}`,
    `Draft: ${payload.draft ? 'yes' : 'no'}`,
    `Changed files: ${payload.changedFiles}`,
    `Additions: ${payload.additions}`,
    `Deletions: ${payload.deletions}`,
    '',
    'Current PR body:',
    payload.currentBody,
    '',
    'Changed files summary:',
    payload.summarizedFiles,
    '',
    'Patch snippets:',
    payload.patchSnippets || 'No patch snippets available.',
  ].join('\n')

  const response = await fetch(MODEL_API_URL, {
    method: 'POST',
    headers: {
      Accept: 'application/vnd.github+json',
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
      'X-GitHub-Api-Version': MODEL_API_VERSION,
    },
    body: JSON.stringify({
      model,
      temperature: 0.1,
      max_tokens: 1400,
      response_format: {
        type: 'json_schema',
        json_schema: {
          name: 'pull_request_autofill',
          schema: {
            type: 'object',
            additionalProperties: false,
            properties: {
              change_summary: {
                type: 'array',
                items: { type: 'string' },
              },
              modules_areas: {
                type: 'array',
                items: { type: 'string' },
              },
              main_files_entry_points: {
                type: 'array',
                items: { type: 'string' },
              },
              before: { type: 'string' },
              after: { type: 'string' },
              risk_notes: {
                type: 'array',
                items: { type: 'string' },
              },
              rollback_plan: {
                type: 'array',
                items: { type: 'string' },
              },
              known_gaps: {
                type: 'array',
                items: { type: 'string' },
              },
            },
            required: [
              'change_summary',
              'modules_areas',
              'main_files_entry_points',
              'before',
              'after',
              'risk_notes',
              'rollback_plan',
              'known_gaps',
            ],
          },
        },
      },
      messages: [
        {
          role: 'system',
          content: systemPrompt,
        },
        {
          role: 'user',
          content: userPrompt,
        },
      ],
    }),
  })

  if (!response.ok) {
    const errorBody = await response.text()

    if (response.status === 403 || response.status === 404 || response.status === 429) {
      console.log(`GitHub Models is not available for this repository or token. Skipping PR autofill. Response: ${errorBody}`)
      return null
    }

    throw new Error(`GitHub Models request failed with status ${response.status}: ${errorBody}`)
  }

  const data = await response.json()
  const rawContent = data?.choices?.[0]?.message?.content
  const content = normalizeModelContent(rawContent)

  if (!content) {
    throw new Error('GitHub Models response did not contain usable content.')
  }

  return JSON.parse(content)
}

function applyGeneratedSections(bodyContent, generated) {
  let updated = bodyContent

  updated = replaceManagedBlock(updated, 'change-summary', formatBulletBlock(
    generated.change_summary,
    (value) => value,
    '- Needs manual confirmation.',
  ))
  updated = replaceManagedBlock(updated, 'modules-areas', formatBulletBlock(
    generated.modules_areas,
    (value) => value,
    '- Needs manual confirmation.',
  ))
  updated = replaceManagedBlock(updated, 'main-files', formatBulletBlock(
    generated.main_files_entry_points,
    (value) => formatPathLikeValue(value),
    '- Needs manual confirmation.',
  ))
  updated = replaceManagedBlock(updated, 'before', formatParagraphBlock(generated.before))
  updated = replaceManagedBlock(updated, 'after', formatParagraphBlock(generated.after))
  updated = replaceManagedBlock(updated, 'risk-notes', formatBulletBlock(
    generated.risk_notes,
    (value) => value,
    '- None identified from the diff.',
  ))
  updated = replaceManagedBlock(updated, 'rollback-plan', formatBulletBlock(
    generated.rollback_plan,
    (value) => value,
    '- Revert the PR and redeploy if needed.',
  ))
  updated = replaceManagedBlock(updated, 'known-gaps', formatBulletBlock(
    generated.known_gaps,
    (value) => value,
    '- None identified from the diff.',
  ))

  return updated
}

function replaceManagedBlock(bodyContent, marker, generatedContent) {
  const pattern = new RegExp(`<!-- pr-ai:${marker}:start -->([\\s\\S]*?)<!-- pr-ai:${marker}:end -->`)
  const match = bodyContent.match(pattern)

  if (!match) {
    return bodyContent
  }

  const currentInner = match[1]?.trim() ?? ''

  if (!canReplaceManagedBlock(currentInner)) {
    return bodyContent
  }

  const replacement = [
    `<!-- pr-ai:${marker}:start -->`,
    generatedContent,
    `<!-- pr-ai:${marker}:end -->`,
  ].join('\n')

  return bodyContent.replace(pattern, replacement)
}

function canReplaceManagedBlock(currentInner) {
  if ('' === currentInner) {
    return true
  }

  const normalized = currentInner.trim()

  return normalized.includes('TODO')
    || normalized.includes('<!-- pr-ai:generated -->')
    || 'Needs manual confirmation.' === normalized
}

function formatBulletBlock(values, formatter, emptyLine) {
  const normalizedValues = Array.isArray(values)
    ? values
      .map((value) => ('string' === typeof value ? value.trim() : ''))
      .filter(Boolean)
      .slice(0, 8)
      .map(formatter)
    : []

  const lines = 0 === normalizedValues.length
    ? [emptyLine]
    : normalizedValues.map((value) => `- ${value}`)

  return ['<!-- pr-ai:generated -->', ...lines].join('\n')
}

function formatParagraphBlock(value) {
  const normalized = 'string' === typeof value && '' !== value.trim()
    ? value.trim()
    : 'Needs manual confirmation.'

  return ['<!-- pr-ai:generated -->', normalized].join('\n')
}

function formatPathLikeValue(value) {
  const normalized = value.trim()

  if (normalized.includes('/') || normalized.endsWith('.php') || normalized.endsWith('.yml') || normalized.startsWith('.github')) {
    return `\`${normalized}\``
  }

  return normalized
}

function normalizeModelContent(rawContent) {
  if ('string' === typeof rawContent) {
    return rawContent
  }

  if (Array.isArray(rawContent)) {
    return rawContent
      .map((entry) => {
        if ('string' === typeof entry) {
          return entry
        }

        if (entry && 'object' === typeof entry && 'text' in entry && 'string' === typeof entry.text) {
          return entry.text
        }

        return ''
      })
      .join('')
      .trim()
  }

  return null
}

function truncate(value, maxLength) {
  if (value.length <= maxLength) {
    return value
  }

  return `${value.slice(0, maxLength - 3)}...`
}

async function updatePullRequest(ownerName, repoName, pullNumber, updatedBody) {
  await githubRequest(
    'PATCH',
    `${apiUrl}/repos/${ownerName}/${repoName}/pulls/${pullNumber}`,
    {
      body: updatedBody,
    },
  )
}

async function githubRequest(method, url, bodyValue) {
  const response = await fetch(url, {
    method,
    headers: {
      Accept: 'application/vnd.github+json',
      Authorization: `Bearer ${token}`,
      'X-GitHub-Api-Version': '2022-11-28',
      ...(undefined !== bodyValue ? { 'Content-Type': 'application/json' } : {}),
    },
    body: undefined !== bodyValue ? JSON.stringify(bodyValue) : undefined,
  })

  if (!response.ok) {
    const errorBody = await response.text()
    throw new Error(`GitHub API request failed with status ${response.status}: ${errorBody}`)
  }

  const responseText = await response.text()
  return '' === responseText ? null : JSON.parse(responseText)
}
