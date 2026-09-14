<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

abstract class Convention
{
    protected const MIN_SAMPLE = 3;

    protected const CONFIDENCE_FLOOR = 0.8;

    /**
     * @return list<ApproachResult>
     */
    public function detect(SourceFiles $files): array
    {
        $result = $this->result($files);

        return $result instanceof ApproachResult ? [$result] : [];
    }

    protected function result(SourceFiles $files): ?ApproachResult
    {
        return null;
    }

    /**
     * @param  callable(string): (array<string, int>|null)  $counts  file contents => per-style occurrence counts, null to skip the file
     */
    protected function electByFile(SourceFiles $files, ?string $in, callable $counts): ?ApproachResult
    {
        $tally = [];
        $paths = [];

        foreach ($files->php($in) as $path) {
            $fileCounts = $counts($files->contents($path));

            if ($fileCounts === null) {
                continue;
            }

            $winner = $this->fileVote($fileCounts);

            if ($winner === null) {
                continue;
            }

            $tally[$winner] = ($tally[$winner] ?? 0) + 1;
            $paths[] = $path;
        }

        return $this->dominant($tally, $paths);
    }

    /**
     * Elect a style by file over an explicit list of files.
     *
     * @param  list<string>  $paths
     * @param  callable(string): (array<string, int>|null)  $counts
     */
    protected function electByFiles(SourceFiles $files, array $paths, callable $counts): ?ApproachResult
    {
        $tally = [];
        $voters = [];

        foreach ($paths as $path) {
            $fileCounts = $counts($files->contents($path));
            $winner = $fileCounts === null ? null : $this->fileVote($fileCounts);

            if ($winner === null) {
                continue;
            }

            $tally[$winner] = ($tally[$winner] ?? 0) + 1;
            $voters[] = $path;
        }

        return $this->dominant($tally, $voters);
    }

    /**
     * Elect a style by counting every occurrence in the given files, for code that lives in few files (listens).
     *
     * @param  list<string>  $paths
     * @param  callable(string): array<string, int>  $counts
     */
    protected function electByOccurrence(SourceFiles $files, array $paths, callable $counts): ?ApproachResult
    {
        $tally = [];
        $voters = [];

        foreach ($paths as $path) {
            $fileCounts = array_filter($counts($files->contents($path)), fn (int $count): bool => $count > 0);

            if ($fileCounts === []) {
                continue;
            }

            foreach ($fileCounts as $approach => $count) {
                $tally[$approach] = ($tally[$approach] ?? 0) + $count;
            }

            $voters[] = $path;
        }

        return $this->dominant($tally, $voters);
    }

    /**
     * @param  array<string, int>  $counts  approach value => occurrences within one file
     */
    protected function fileVote(array $counts): ?string
    {
        $counts = array_filter($counts, fn (int $count): bool => $count > 0);

        if ($counts === []) {
            return null;
        }

        arsort($counts);
        $ranked = array_values($counts);

        if (isset($ranked[1]) && $ranked[1] === $ranked[0]) {
            return null;
        }

        return array_key_first($counts);
    }

    /**
     * @param  array<string, int>  $tally  approach value => votes
     * @param  list<string>  $paths
     */
    protected function dominant(array $tally, array $paths): ?ApproachResult
    {
        $tally = array_filter($tally, fn (int $votes): bool => $votes > 0);
        $total = array_sum($tally);

        if ($total < static::MIN_SAMPLE) {
            return null;
        }

        arsort($tally);
        $winner = (string) array_key_first($tally);
        $votes = $tally[$winner];

        if ($votes / $total < static::CONFIDENCE_FLOOR) {
            return null;
        }

        return new ApproachResult(
            approach: Approach::from($winner),
            confidence: $votes / $total,
            matched: $votes,
            total: $total,
            paths: $paths,
        );
    }
}
