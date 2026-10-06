<?php

declare(strict_types=1);

require dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

$traceDirectory = __DIR__.DIRECTORY_SEPARATOR.'Traces';

if (! is_dir($traceDirectory) && ! mkdir($traceDirectory, 0777, true) && ! is_dir($traceDirectory)) {
    throw new RuntimeException('The Pest browser trace directory could not be prepared.');
}
