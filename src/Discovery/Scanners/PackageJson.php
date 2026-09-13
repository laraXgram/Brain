<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Scanners;

use LaraGram\Brain\Discovery\Package;
use LaraGram\Brain\Discovery\PackageCollection;

class PackageJson extends JsPackageScanner
{
    public function scan(): PackageCollection
    {
        $packages = new PackageCollection;

        foreach ($this->directDependencies() as $name => $meta) {
            $constraint = $meta['constraint'];

            $packages->push(new Package(
                name: $name,
                version: self::normalizeVersion($constraint),
                source: $this->source(),
                dev: $meta['isDev'],
                direct: true,
                constraint: $constraint,
                path: $this->computePath($name),
            ));
        }

        return $packages;
    }
}
