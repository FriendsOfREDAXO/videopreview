<?php

/**
 * Sind die nötigen Shell-Tools verfügbar?
 */
$shellResponse = shell_exec('command -v ffmpeg');
if (null === $shellResponse) {
    throw new rex_functional_exception('Missing required shell-command "ffmpeg".');
}
