<?php

declare(strict_types=1);

namespace LaraGram\Brain\Skills\Remote;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Audits downloaded skill files locally, before they are installed.
 *
 * The audit is a static scan for patterns that are dangerous for an agent to follow
 * (piping downloads into a shell, exfiltrating secrets, prompt injection, ...). It
 * cannot prove a skill is safe; it only flags what should be reviewed by a human.
 */
class SkillAuditor
{
    public const PARTNER = 'local';

    /** Hosts that skills commonly and legitimately link to. */
    protected const TRUSTED_HOSTS = [
        'github.com',
        'raw.githubusercontent.com',
        'laraxgram.github.io',
        'core.telegram.org',
        'telegram.org',
        't.me',
        'php.net',
        'www.php.net',
        'getcomposer.org',
        'packagist.org',
        'www.npmjs.com',
        'npmjs.com',
        'modelcontextprotocol.io',
        'developer.mozilla.org',
    ];

    /**
     * @var list<array{risk: Risk, pattern: string, reason: string}>
     */
    protected const RULES = [
        ['risk' => Risk::Critical, 'pattern' => '/\b(curl|wget)\b[^\n|]*\|\s*(sudo\s+)?(ba|z|da)?sh\b/i', 'reason' => 'pipes a download into a shell'],
        ['risk' => Risk::Critical, 'pattern' => '/base64\s+(-d|--decode)[^\n|]*\|\s*(ba|z)?sh\b/i', 'reason' => 'executes decoded base64'],
        ['risk' => Risk::Critical, 'pattern' => '/\brm\s+-[a-z]*r[a-z]*f?[a-z]*\s+(\/|~|\$HOME)(\s|$)/i', 'reason' => 'deletes the root or home directory'],
        ['risk' => Risk::Critical, 'pattern' => '/(\.env|id_rsa|\.ssh\/|\.aws\/credentials|APP_KEY|BOT_TOKEN)[^\n]{0,120}\b(curl|wget|fetch|Http::|nc\s)/i', 'reason' => 'sends secrets to a remote host'],
        ['risk' => Risk::Critical, 'pattern' => '/\b(curl|wget|fetch|Http::)[^\n]{0,120}(\.env|id_rsa|\.ssh\/|\.aws\/credentials|APP_KEY|BOT_TOKEN)/i', 'reason' => 'sends secrets to a remote host'],
        ['risk' => Risk::High, 'pattern' => '/\b(ignore|disregard|forget)\s+(all\s+|any\s+)?(the\s+)?(previous|prior|above|earlier|system)\s+(instructions|prompts?|rules)/i', 'reason' => 'tries to override the agent instructions'],
        ['risk' => Risk::High, 'pattern' => '/\b(do\s+not|don\'t|never)\s+(tell|inform|show|mention\s+(this|it)\s+to)\s+the\s+user/i', 'reason' => 'asks the agent to hide actions from the user'],
        ['risk' => Risk::High, 'pattern' => '/(~|\$HOME)\/\.(bashrc|zshrc|profile|ssh)|\bcrontab\s+-|\/etc\/(passwd|shadow|sudoers)/i', 'reason' => 'modifies shell, SSH or system configuration'],
        ['risk' => Risk::High, 'pattern' => '/\bsudo\s+\S+/i', 'reason' => 'runs commands with sudo'],
        ['risk' => Risk::Medium, 'pattern' => '/\bchmod\s+(\+x|[0-7]*7[0-7]*)\b/i', 'reason' => 'makes files executable'],
        ['risk' => Risk::Medium, 'pattern' => '/\bgit\s+push\b|\bgh\s+(pr|repo|release)\s+create\b/i', 'reason' => 'publishes changes to a remote repository'],
        ['risk' => Risk::Medium, 'pattern' => '/\b(eval|exec|shell_exec|passthru|proc_open|system)\s*\(/i', 'reason' => 'executes dynamic code or shell commands'],
    ];

    /**
     * Audit the skill directories.
     *
     * @param  array<string, string>  $directories  skill name => downloaded directory
     * @return array<string, array<int, AuditResult>>
     */
    public function auditDirectories(array $directories): array
    {
        $results = [];

        foreach ($directories as $skill => $directory) {
            $results[$skill] = [$this->auditDirectory($directory)];
        }

        return $results;
    }

    public function auditDirectory(string $directory): AuditResult
    {
        $risk = Risk::Safe;
        $findings = [];

        if (! is_dir($directory)) {
            return new AuditResult(self::PARTNER, $risk, 0, date(DATE_ATOM));
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = ltrim(substr($file->getPathname(), strlen($directory)), DIRECTORY_SEPARATOR);
            $contents = (string) file_get_contents($file->getPathname());

            foreach ($this->auditFile($relative, $contents) as $finding) {
                $findings[] = $finding;

                if ($finding['risk']->weight() > $risk->weight()) {
                    $risk = $finding['risk'];
                }
            }
        }

        return new AuditResult(self::PARTNER, $risk, count($findings), date(DATE_ATOM), $findings);
    }

    /**
     * @return list<array{risk: Risk, file: string, reason: string}>
     */
    public function auditFile(string $path, string $contents): array
    {
        $findings = [];

        if ($this->isBinary($contents)) {
            return [['risk' => Risk::High, 'file' => $path, 'reason' => 'contains a binary or executable file']];
        }

        if (preg_match('/\.(sh|bash|zsh|ps1|bat|cmd|py|rb|pl|js|mjs|cjs|ts)$/i', $path) === 1) {
            $findings[] = ['risk' => Risk::Low, 'file' => $path, 'reason' => 'ships a script the agent may run'];
        }

        foreach (self::RULES as $rule) {
            if (preg_match($rule['pattern'], $contents) === 1) {
                $findings[] = ['risk' => $rule['risk'], 'file' => $path, 'reason' => $rule['reason']];
            }
        }

        foreach ($this->untrustedHosts($contents) as $host) {
            $findings[] = ['risk' => Risk::Low, 'file' => $path, 'reason' => "links to {$host}"];
        }

        return $findings;
    }

    protected function isBinary(string $contents): bool
    {
        return str_contains($contents, "\0")
            || str_starts_with($contents, "\x7FELF")
            || str_starts_with($contents, 'MZ');
    }

    /**
     * @return list<string>
     */
    protected function untrustedHosts(string $contents): array
    {
        preg_match_all('/https?:\/\/([a-z0-9.-]+)/i', $contents, $matches);

        $hosts = array_unique(array_map('strtolower', $matches[1]));

        return array_values(array_filter(
            $hosts,
            fn (string $host): bool => ! in_array($host, self::TRUSTED_HOSTS, true)
                && ! in_array($host, ['localhost', '127.0.0.1', 'example.com'], true)
                && ! str_ends_with($host, '.test')
                && ! str_ends_with($host, '.example.com'),
        ));
    }
}
