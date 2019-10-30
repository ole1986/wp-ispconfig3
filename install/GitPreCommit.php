<?php

namespace Ole1986;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class GitPreCommit
{
    private static $preCommitFile = '.git/hooks/pre-commit';
    public static function install(Event $event)
    {
        echo "Installing pre-commit script for git\n";

        if (file_exists(self::$preCommitFile)) {
            unlink(self::$preCommitFile);
        }

        symlink('../../install/pre-commit', self::$preCommitFile);
        chmod('install/pre-commit', 0755);
    }
}
