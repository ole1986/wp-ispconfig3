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
        $content = "#!/bin/sh\nvendor/bin/phpcs .";
        file_put_contents(self::$preCommitFile, $content);

        echo "Make pre-commit executable\n";
        chmod(self::$preCommitFile, 0755);
    }
}
