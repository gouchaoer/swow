<?php
/**
 * This file is part of Swow
 *
 * @link     https://github.com/swow/swow
 * @contact  twosee <twose@qq.com>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code
 */

namespace Swow\Tools;

use RuntimeException;
use function Swow\Util\FileSystem\remove;

require_once __DIR__ . '/tools.php';

class DependencyManager
{
    protected const TMP_DIR = '/tmp';

    public static function sync(string $name, string $url, string $sourceDir, string $targetDir): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            throw new RuntimeException('Linux only');
        }
        if (!`cd {$targetDir} && git rev-parse HEAD`) {
            throw new RuntimeException("Unable to find git repo in {$targetDir}");
        }
        $tmpBackupDir = static::TMP_DIR . '/swow_deps_backup_' . date('YmdHis');
        if (!is_dir($tmpBackupDir) && !mkdir($tmpBackupDir)) {
            throw new RuntimeException("Unable to create tmp backup dir '{$tmpBackupDir}'");
        }
        if (substr($url, -3) === 'git') {
            $tmpSourceDir = static::TMP_DIR . "/swow_deps/{$name}";
            if (is_dir($tmpSourceDir)) {
                remove($tmpSourceDir);
            }
            if (!mkdir($tmpSourceDir, 0777, true)) {
                throw new RuntimeException("Unable to create tmp source dir '{$tmpSourceDir}'");
            }
            defer($defer, function () use ($tmpSourceDir) {
                if (is_dir($tmpSourceDir)) {
                    remove($tmpSourceDir);
                }
            });
            `git clone --depth=1 {$url} {$tmpSourceDir}`;
            if (!is_dir("{$tmpSourceDir}/.git")) {
                throw new RuntimeException("Clone source files of {$name} failed");
            }
            $version = trim(`cd {$tmpSourceDir} && git rev-parse HEAD`);
            if (strlen($version) !== 40) {
                throw new RuntimeException("Invalid git version {$version}");
            }
            remove("{$tmpSourceDir}/.git");
        } else {
            throw new RuntimeException('Unsupported now');
        }
        if (file_exists($targetDir)) {
            if (!rename($targetDir, $tmpBackupDir)) {
                throw new RuntimeException("Backup from '{$targetDir}' to '{$tmpBackupDir}' failed");
            }
        }
        if (!rename($tmpSourceDir, $targetDir)) {
            throw new RuntimeException("Update dep files from '{$tmpSourceDir}' to {$targetDir} failed");
        }
        `cd {$targetDir} && git add --ignore-errors -A`;

        return $version;
    }
}
