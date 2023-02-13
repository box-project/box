<?php declare(strict_types=1);











namespace Composer\Script;







class ScriptEvents
{







public const PRE_INSTALL_CMD = 'pre-install-cmd';








public const POST_INSTALL_CMD = 'post-install-cmd';








public const PRE_UPDATE_CMD = 'pre-update-cmd';








public const POST_UPDATE_CMD = 'post-update-cmd';








public const PRE_STATUS_CMD = 'pre-status-cmd';








public const POST_STATUS_CMD = 'post-status-cmd';








public const PRE_AUTOLOAD_DUMP = 'pre-autoload-dump';








public const POST_AUTOLOAD_DUMP = 'post-autoload-dump';








public const POST_ROOT_PACKAGE_INSTALL = 'post-root-package-install';









public const POST_CREATE_PROJECT_CMD = 'post-create-project-cmd';








public const PRE_ARCHIVE_CMD = 'pre-archive-cmd';








public const POST_ARCHIVE_CMD = 'post-archive-cmd';
}
