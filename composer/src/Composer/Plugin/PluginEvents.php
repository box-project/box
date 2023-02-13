<?php declare(strict_types=1);











namespace Composer\Plugin;






class PluginEvents
{








public const INIT = 'init';









public const COMMAND = 'command';









public const PRE_FILE_DOWNLOAD = 'pre-file-download';









public const POST_FILE_DOWNLOAD = 'post-file-download';









public const PRE_COMMAND_RUN = 'pre-command-run';










public const PRE_POOL_CREATE = 'pre-pool-create';
}
