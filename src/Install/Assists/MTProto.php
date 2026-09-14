<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Assists;

use LaraGram\Brain\Discovery\ProjectManager;
use LaraGram\Brain\Support\PackageRegistry;

class MTProto
{
    public function __construct(private ProjectManager $project)
    {
        //
    }

    /**
     * The session names declared in config/mtproto.php (never their credentials).
     *
     * @return list<string>
     */
    public function sessions(): array
    {
        return array_map('strval', array_keys((array) config('mtproto.sessions', [])));
    }

    /**
     * Determine if MTProto sessions are run by Surge (the pump process), so calls from workers go over RPC.
     */
    public function usesSurge(): bool
    {
        return $this->project->php()->uses(PackageRegistry::SURGE)
            && (bool) config('mtproto.surge.autostart', true);
    }
}
