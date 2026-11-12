<?php

namespace App\Jobs;

use App\Domain\Documents\Services\DocumentGenerator;
use App\Support\Text;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Generation of one document on the queue "documents" (D-220, D-222; DM-04): three attempts, 1, 5 and
 * 15 minutes apart, at most 5 minutes each (config/horizon.php). It carries only the id: a retry
 * reads the document again, and a replaced document is left alone (DocumentGenerator).
 */
final class GenerateDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $documentId)
    {
        $this->onQueue('documents');
    }

    public function handle(DocumentGenerator $generator): void
    {
        $generator->generate($this->documentId);
    }

    public function failed(?Throwable $exception): void
    {
        app(DocumentGenerator::class)->fail(
            $this->documentId,
            Text::get('documents.failures.generation', ['error' => $exception?->getMessage() ?? '']),
        );
    }
}
