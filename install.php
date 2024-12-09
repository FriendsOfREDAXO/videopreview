<?php

/**
 * Sind die nötigen Shell-Tools verfügbar?
 */
if (!function_exists('shell_exec')) {
    throw new rex_functional_exception('shell_exec function is not available. Please check your PHP configuration.');
}

$shellResponse = shell_exec('command -v ffmpeg');
if (null === $shellResponse) {
    throw new rex_functional_exception('Missing required shell-command "ffmpeg".');
}
