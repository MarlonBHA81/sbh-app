<?php

namespace App\Services\Observability;

use App\Contracts\IssueTracker;
use App\Observability\Alert;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Files triage issues on GitHub. A hidden fingerprint marker in the issue body
 * lets recurrences find and comment on the existing open issue instead of
 * opening a duplicate. The issue is the hand-off point: a fix-agent watching the
 * repo's `auto-triage` label picks it up and opens a PR for human review.
 */
class GithubIssueTracker implements IssueTracker
{
    /**
     * @param  array{token?: ?string, repo?: ?string, labels?: array<int, string>, api_url?: string, timeout?: int}  $config
     */
    public function __construct(private array $config) {}

    public function report(Alert $alert): ?string
    {
        $token = (string) ($this->config['token'] ?? '');
        $repo = (string) ($this->config['repo'] ?? '');

        if ($token === '' || $repo === '') {
            Log::warning('GithubIssueTracker: missing token or repo; alert not filed', [
                'fingerprint' => $alert->fingerprint,
            ]);

            return null;
        }

        $marker = "<!-- sbh-fp:{$alert->fingerprint} -->";

        $existing = $this->findOpenIssue($repo, $alert->fingerprint);
        if ($existing !== null) {
            $this->comment($repo, $existing['number'], $alert);

            return (string) ($existing['html_url'] ?? null);
        }

        $response = $this->client()->post("/repos/{$repo}/issues", [
            'title' => $this->issueTitle($alert),
            'body' => $this->issueBody($alert, $marker),
            'labels' => array_values($this->config['labels'] ?? []),
        ])->throw();

        return (string) ($response->json('html_url') ?? null);
    }

    /**
     * Find an existing open triage issue for this fingerprint via the search API.
     *
     * @return array{number: int, html_url: string}|null
     */
    private function findOpenIssue(string $repo, string $fingerprint): ?array
    {
        $query = sprintf('repo:%s is:issue is:open "sbh-fp:%s" in:body', $repo, $fingerprint);

        $response = $this->client()->get('/search/issues', ['q' => $query]);

        // Search can 403 on rate limits or 422 on a bad query — treat any
        // non-success as "not found" and let a new issue be opened rather than
        // failing the whole job.
        if (! $response->successful()) {
            Log::info('GithubIssueTracker: issue search unsuccessful, treating as new', [
                'status' => $response->status(),
                'fingerprint' => $fingerprint,
            ]);

            return null;
        }

        $item = $response->json('items.0');

        return $item === null ? null : [
            'number' => (int) $item['number'],
            'html_url' => (string) $item['html_url'],
        ];
    }

    private function comment(string $repo, int $number, Alert $alert): void
    {
        $this->client()->post("/repos/{$repo}/issues/{$number}/comments", [
            'body' => 'Recurred'.($alert->environment ? " in `{$alert->environment}`" : '').'.'.
                ($alert->url ? "\n\n[View in monitor]({$alert->url})" : ''),
        ])->throw();
    }

    private function issueTitle(Alert $alert): string
    {
        $prefix = $alert->environment ? "[{$alert->environment}] " : '';

        // GitHub issue titles are capped well above this, but keep them scannable.
        return $prefix.mb_strimwidth($alert->title, 0, 200, '…');
    }

    private function issueBody(Alert $alert, string $marker): string
    {
        $lines = [
            $marker,
            '',
            '**Automated triage** — filed from an observability alert. A fix-agent may open a PR for this; review before merging.',
            '',
            "- **Level:** {$alert->level}",
        ];

        if ($alert->environment) {
            $lines[] = "- **Environment:** {$alert->environment}";
        }
        if ($alert->url) {
            $lines[] = "- **Monitor:** {$alert->url}";
        }

        $lines[] = '';
        $lines[] = '```';
        $lines[] = $alert->body;
        $lines[] = '```';

        return implode("\n", $lines);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) ($this->config['api_url'] ?? 'https://api.github.com'))
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->withToken((string) ($this->config['token'] ?? ''))
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'SBH-Observability',
            ]);
    }
}
