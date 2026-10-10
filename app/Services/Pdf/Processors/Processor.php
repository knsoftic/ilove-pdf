<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\JobContext;
use App\Services\Pdf\ProcessResult;

/**
 * Server-side implementation of a tool.
 */
interface Processor
{
    /**
     * Capabilities needed on the server. Each entry is an alternative set of
     * capability names (see Capabilities::has()); the processor can run when
     * every name of at least one set is available. An empty array means the
     * processor only needs PHP.
     *
     * @return list<list<string>>
     */
    public static function requirements(array $params = []): array;

    /**
     * Should the job be pushed to the queue (heavy work)?
     */
    public static function queued(): bool;

    public function handle(JobContext $context): ProcessResult;
}
