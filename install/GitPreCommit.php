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
        $content = "#!/bin/sh\n";
        $content.= "files=()\n";
        $content.= 'for f in $(git diff --name-only --cached); do files+=($f); done' . "\n";
        $content.= '[ ${#files[@]} -gt 0 ] && vendor/bin/phpcs ${files[@]}' . "\n";

        file_put_contents(self::$preCommitFile, $content);

        echo "Make pre-commit executable\n";
        chmod(self::$preCommitFile, 0755);
    }
}
