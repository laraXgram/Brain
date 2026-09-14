<?php

declare(strict_types=1);

namespace LaraGram\Brain\Console;

use LaraGram\Brain\Docs\DocsIndex;
use LaraGram\Brain\Docs\Documentation;
use LaraGram\Console\Command;
use LaraGram\Support\Facades\File;
use LaraGram\Support\Facades\Http;
use PharData;
use Throwable;

class DocsCommand extends Command
{
    protected $signature = 'brain:docs
        {--docs-version=4 : The LaraGram documentation version}
        {--update : Download the latest documentation from the LaraGram documentation repository}';

    protected $description = 'Show or update the LaraGram documentation used by the search-docs tool';

    protected string $archiveUrl = 'https://codeload.github.com/laraxgram/laraxgram.github.io/tar.gz/refs/heads/master';

    public function handle(): int
    {
        $version = (int) $this->option('docs-version');
        $documentation = new Documentation($version);

        if ($this->option('update')) {
            if (! $this->update($version)) {
                return self::FAILURE;
            }
        }

        $path = $documentation->path();

        if ($path === null) {
            $this->error("No documentation is available for LaraGram v{$version}. Run [php laragram brain:docs --update].");

            return self::FAILURE;
        }

        $index = DocsIndex::load($documentation);

        $this->components->twoColumnDetail('Version', "v{$version}");
        $this->components->twoColumnDetail('Source', str_starts_with($path, Documentation::bundledPath($version)) ? 'Bundled with Brain' : 'Downloaded (brain:docs --update)');
        $this->components->twoColumnDetail('Pages', (string) count($documentation->pages()));
        $this->components->twoColumnDetail('Indexed sections', (string) count($index->sections()));

        return self::SUCCESS;
    }

    protected function update(int $version): bool
    {
        $temporary = storage_path('framework'.DIRECTORY_SEPARATOR.'brain'.DIRECTORY_SEPARATOR.'docs-download-'.getmypid());
        $archive = $temporary.'.tar.gz';

        try {
            File::ensureDirectoryExists(dirname($archive));

            $this->components->task('Downloading the LaraGram documentation', function () use ($archive): bool {
                return Http::timeout(60)->sink($archive)->get($this->archiveUrl)->successful();
            });

            if (! is_file($archive) || filesize($archive) === 0) {
                $this->error('The documentation could not be downloaded.');

                return false;
            }

            (new PharData($archive))->extractTo($temporary, null, true);

            $source = collect(glob($temporary.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'v'.$version) ?: [])->first();

            if (! is_string($source) || glob($source.DIRECTORY_SEPARATOR.'*.md') === []) {
                $this->error("The downloaded documentation has no v{$version} pages.");

                return false;
            }

            $target = Documentation::storagePath($version);

            File::deleteDirectory($target);
            File::ensureDirectoryExists($target);

            foreach (glob($source.DIRECTORY_SEPARATOR.'*.md') ?: [] as $page) {
                File::copy($page, $target.DIRECTORY_SEPARATOR.basename($page));
            }

            $this->components->info('The documentation was updated.');

            return true;
        } catch (Throwable $e) {
            $this->error('The documentation could not be updated: '.$e->getMessage());

            return false;
        } finally {
            File::delete($archive);
            File::deleteDirectory($temporary);
        }
    }
}
