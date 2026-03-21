<?php

namespace App\Logging\Tap;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger as MonologLogger;

/**
 * Makes the "daily_json" channel write one JSON object per line (Loki, ELK, CloudWatch, etc.).
 */
class JsonFormatterTap
{
    public function __invoke(Logger $logger): void
    {
        $underlying = $logger->getLogger();
        if (! $underlying instanceof MonologLogger) {
            return;
        }

        $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
        foreach ($underlying->getHandlers() as $handler) {
            $handler->setFormatter($formatter);
        }
    }
}
