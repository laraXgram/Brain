<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery;

use LaraGram\Support\Str;
use LaraGram\Brain\Discovery\Detectors\AgentsDetector;
use LaraGram\Brain\Discovery\Detectors\ApproachDetector;
use LaraGram\Brain\Discovery\Detectors\EditorsDetector;
use LaraGram\Brain\Discovery\Detectors\FrontendDetector;
use LaraGram\Brain\Discovery\Detectors\StackDetector;
use LaraGram\Brain\Discovery\Detectors\StarterKitDetector;
use LaraGram\Brain\Discovery\Ecosystems\Ecosystem;
use LaraGram\Brain\Discovery\Ecosystems\JsEcosystem;
use LaraGram\Brain\Discovery\Enums\Agent;
use LaraGram\Brain\Discovery\Enums\Editor;
use LaraGram\Brain\Discovery\Enums\Frontend;
use LaraGram\Brain\Discovery\Enums\Stack;
use LaraGram\Brain\Discovery\Scanners\ComposerLock;
use LaraGram\Brain\Discovery\Scanners\JsLockfile;
use LaraGram\Brain\Discovery\Support\ApproachSet;
use LaraGram\Brain\Discovery\Support\EnumSet;
use Throwable;

class ProjectScan
{
    protected ?ApproachSet $approaches = null;

    /**
     * @param  EnumSet<Stack>  $stacks
     * @param  EnumSet<Frontend>  $frontends
     * @param  EnumSet<Agent>  $agents
     * @param  EnumSet<Editor>  $editors
     */
    public function __construct(
        protected string $basePath,
        protected Ecosystem $php,
        protected JsEcosystem $js,
        protected EnumSet $stacks,
        protected EnumSet $frontends,
        protected EnumSet $agents,
        protected EnumSet $editors,
        protected ?string $minimumPhpVersion = null,
        protected ?string $starterKit = null,
    ) {
        //
    }

    public function php(): Ecosystem
    {
        return $this->php;
    }

    public function minimumPhpVersion(): string
    {
        return $this->minimumPhpVersion ?? PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
    }

    public function js(): JsEcosystem
    {
        return $this->js;
    }

    /** @return EnumSet<Stack> */
    public function stacks(): EnumSet
    {
        return $this->stacks;
    }

    /** @return EnumSet<Frontend> */
    public function frontends(): EnumSet
    {
        return $this->frontends;
    }

    /** @return EnumSet<Agent> */
    public function agents(): EnumSet
    {
        return $this->agents;
    }

    /** @return EnumSet<Editor> */
    public function editors(): EnumSet
    {
        return $this->editors;
    }

    public function starterKit(): ?string
    {
        return $this->starterKit;
    }

    public function approaches(): ApproachSet
    {
        return $this->approaches ??= new ApproachSet(
            ApproachDetector::detect($this->basePath),
        );
    }

    public static function scan(?string $basePath = null): self
    {
        $basePath = self::normalizeBasePath($basePath);

        $composer = new ComposerLock($basePath);
        $phpPackages = $composer->scan();

        $jsLockfile = new JsLockfile($basePath);
        $jsPackages = $jsLockfile->scan();

        $php = new Ecosystem($phpPackages);
        $js = new JsEcosystem($jsPackages, $jsLockfile->committedManager());

        return new self(
            $basePath,
            $php,
            $js,
            new EnumSet(StackDetector::detect($php, $js, $basePath)),
            new EnumSet(FrontendDetector::detect($js)),
            new EnumSet(AgentsDetector::detect($basePath)),
            new EnumSet(EditorsDetector::detect($basePath)),
            $composer->minimumPhpVersion(),
            StarterKitDetector::detect($basePath),
        );
    }

    /**
     * @internal
     */
    public static function normalizeBasePath(?string $basePath): string
    {
        return Str::finish($basePath ?? self::defaultBasePath(), DIRECTORY_SEPARATOR);
    }

    private static function defaultBasePath(): string
    {
        if (function_exists('base_path')) {
            try {
                return base_path();
            } catch (Throwable) {
                //
            }
        }

        return getcwd() ?: '.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'php' => array_map(fn (Package $package): array => $package->toArray(), $this->php->packages()->all()),
            'minimumPhpVersion' => $this->minimumPhpVersion(),
            'js' => array_map(fn (Package $package): array => $package->toArray(), $this->js->packages()->all()),
            'stacks' => $this->stacks->values(),
            'frontends' => $this->frontends->values(),
            'agents' => $this->agents->values(),
            'editors' => $this->editors->values(),
            'jsPackageManager' => $this->js->packageManager()?->value,
            'starterKit' => $this->starterKit,
        ];
    }

    public function json(): string
    {
        return self::encode($this->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $properties = get_object_vars($this);
        unset($properties['approaches']);

        return $properties;
    }

    /**
     * @param  array{
     *     basePath: string,
     *     php: Ecosystem,
     *     js: JsEcosystem,
     *     stacks: EnumSet<Stack>,
     *     frontends: EnumSet<Frontend>,
     *     agents: EnumSet<Agent>,
     *     editors: EnumSet<Editor>,
     *     minimumPhpVersion: ?string,
     *     starterKit?: string|null,
     * }  $properties
     */
    public function __unserialize(array $properties): void
    {
        foreach ($properties as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }

        $this->starterKit = $properties['starterKit'] ?? null;

        $this->approaches = null;
    }
}
