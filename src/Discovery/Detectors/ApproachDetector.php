<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Detectors\Approaches\AuthorizationStyle;
use LaraGram\Brain\Discovery\Detectors\Approaches\AuthRetrievalStyle;
use LaraGram\Brain\Discovery\Detectors\Approaches\CommandSignatureSyntax;
use LaraGram\Brain\Discovery\Detectors\Approaches\Convention;
use LaraGram\Brain\Discovery\Detectors\Approaches\EnumCasing;
use LaraGram\Brain\Discovery\Detectors\Approaches\MassAssignment;
use LaraGram\Brain\Discovery\Detectors\Approaches\ModelKeyStyle;
use LaraGram\Brain\Discovery\Detectors\Approaches\ValidationStyle;
use LaraGram\Brain\Discovery\Detectors\Approaches\ValidationSyntax;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class ApproachDetector
{
    public function __construct(protected SourceFiles $files)
    {
        //
    }

    /**
     * @return list<ApproachResult>
     */
    public static function detect(string $basePath): array
    {
        return (new self(new SourceFiles($basePath)))->all();
    }

    /**
     * @return list<Convention>
     */
    protected function conventions(): array
    {
        return [
            new MassAssignment,
            new EnumCasing,
            new ValidationSyntax,
            new ValidationStyle,
            new CommandSignatureSyntax,
            new AuthorizationStyle,
            new AuthRetrievalStyle,
            new ModelKeyStyle,
        ];
    }

    /**
     * @return list<ApproachResult>
     */
    public function all(): array
    {
        $results = [];

        foreach ($this->conventions() as $convention) {
            $results = [...$results, ...$convention->detect($this->files)];
        }

        return $results;
    }
}
