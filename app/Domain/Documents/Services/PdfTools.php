<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Data\PdfFont;
use App\Domain\Documents\Data\PdfMetadata;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Command-line tools on the generated PDF (progettazione-tecnica §5.5.2 steps 6-7, M09 §7 and §9):
 * qpdf optimises (linearisation for the "fast web view", object streams, compression, metadata) and
 * checks the file; pdfinfo counts the pages; pdffonts lists the fonts.
 */
final class PdfTools
{
    private const TIMEOUT = 120;

    /**
     * Writes the optimised copy of a PDF: linearised, with object streams and compressed streams,
     * and with title, author and subject in the document information.
     */
    public function optimize(string $input, string $output, PdfMetadata $metadata): void
    {
        $update = dirname($output).'/'.pathinfo($output, PATHINFO_FILENAME).'-metadata.json';
        file_put_contents($update, $this->metadataUpdate($input, $metadata));

        try {
            $this->run([
                $this->binary('documents.qpdf_binary', 'qpdf'),
                $input,
                '--update-from-json='.$update,
                '--linearize',
                '--object-streams=generate',
                '--compress-streams=y',
                '--recompress-flate',
                $output,
            ]);
        } finally {
            @unlink($update);
        }
    }

    /**
     * qpdf --check: the structure of the PDF is valid (any error or warning throws).
     */
    public function check(string $pdf): void
    {
        $this->run([$this->binary('documents.qpdf_binary', 'qpdf'), '--check', $pdf]);
    }

    public function isLinearized(string $pdf): bool
    {
        $process = new Process([$this->binary('documents.qpdf_binary', 'qpdf'), '--check-linearization', $pdf]);
        $process->setTimeout(self::TIMEOUT)->run();

        return $process->isSuccessful() && str_contains($process->getOutput(), 'no linearization errors');
    }

    public function pageCount(string $pdf): int
    {
        $output = $this->run([$this->binary('documents.pdfinfo_binary', 'pdfinfo'), $pdf]);

        if (preg_match('/^Pages:\s+(\d+)$/m', $output, $match) !== 1) {
            throw new RuntimeException("pdfinfo did not report the pages of {$pdf}.");
        }

        return (int) $match[1];
    }

    /**
     * @return array<string, string> document information as shown by pdfinfo (Title, Author…)
     */
    public function info(string $pdf): array
    {
        $info = [];

        foreach (explode("\n", $this->run([$this->binary('documents.pdfinfo_binary', 'pdfinfo'), $pdf])) as $line) {
            if (preg_match('/^([A-Za-z ]+):\s+(.*)$/', $line, $match) === 1) {
                $info[trim($match[1])] = trim($match[2]);
            }
        }

        return $info;
    }

    /**
     * @return list<PdfFont>
     */
    public function fonts(string $pdf): array
    {
        $lines = explode("\n", trim($this->run([$this->binary('documents.pdffonts_binary', 'pdffonts'), $pdf])));
        $fonts = [];

        // Two header lines, then: name, type (one or more words), encoding, emb, sub, uni, object, generation.
        foreach (array_slice($lines, 2) as $line) {
            $columns = preg_split('/\s+/', trim($line)) ?: [];

            if (count($columns) < 8) {
                continue;
            }

            $count = count($columns);
            $fonts[] = new PdfFont($columns[0], $columns[$count - 5] === 'yes', $columns[$count - 4] === 'yes');
        }

        return $fonts;
    }

    /**
     * qpdf JSON (version 2) that replaces the document information dictionary, keeping what
     * Chromium wrote (producer, dates) and adding title, author and subject.
     */
    private function metadataUpdate(string $pdf, PdfMetadata $metadata): string
    {
        try {
            /** @var array{qpdf: array{0: array<string, mixed>, 1: array<string, mixed>}} $json */
            $json = json_decode(
                $this->run([$this->binary('documents.qpdf_binary', 'qpdf'), '--json=2', '--json-key=qpdf', $pdf]),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException("qpdf returned an invalid JSON for {$pdf}.", previous: $exception);
        }

        [$header, $objects] = $json['qpdf'];
        $trailer = $objects['trailer'] ?? null;
        $reference = is_array($trailer) && is_array($trailer['value'] ?? null) ? ($trailer['value']['/Info'] ?? null) : null;

        if (! is_string($reference)) {
            throw new RuntimeException("The PDF {$pdf} has no document information dictionary.");
        }

        $current = $objects['obj:'.$reference] ?? null;
        $info = is_array($current) && is_array($current['value'] ?? null) ? $current['value'] : [];

        $info['/Title'] = 'u:'.$metadata->title;
        $info['/Author'] = 'u:'.$metadata->author;
        $info['/Subject'] = 'u:'.$metadata->subject;

        return json_encode([
            'qpdf' => [
                [
                    'jsonversion' => 2,
                    'pushedinheritedpageresources' => false,
                    'calledgetallpages' => false,
                    'maxobjectid' => $header['maxobjectid'] ?? 0,
                ],
                ['obj:'.$reference => ['value' => $info]],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(self::TIMEOUT)->mustRun();

        return $process->getOutput();
    }

    private function binary(string $key, string $default): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
