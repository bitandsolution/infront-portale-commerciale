<?php

namespace App\Domain\Content\Data;

/**
 * The text of a block of a slide composed with blocks (D-430; DM-07), parsed from the plain text the
 * user writes: a paragraph per line, "- " or "• " at the start of a line makes a bulleted item,
 * "**…**" makes bold. An empty line is a gap between paragraphs. Never HTML: the runs are escaped by
 * the view like every datum (D-176).
 */
final readonly class CustomText
{
    /**
     * @param  list<CustomParagraph>  $paragraphs
     */
    public function __construct(public array $paragraphs) {}

    public static function parse(string $text): self
    {
        $paragraphs = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            $line = rtrim($line);
            $bullet = false;

            if (preg_match('/^\s*[-•]\s+/u', $line, $match) === 1) {
                $bullet = true;
                $line = mb_substr($line, mb_strlen($match[0]));
            }

            $paragraphs[] = new CustomParagraph($bullet, self::runs(trim($line)));
        }

        while ($paragraphs !== [] && $paragraphs[array_key_last($paragraphs)]->isEmpty()) {
            array_pop($paragraphs);
        }

        while ($paragraphs !== [] && $paragraphs[0]->isEmpty()) {
            array_shift($paragraphs);
        }

        return new self(array_values($paragraphs));
    }

    /**
     * Bold toggled by every "**"; an unmatched last "**" stays as written.
     *
     * @return list<array{text: string, bold: bool}>
     */
    private static function runs(string $line): array
    {
        $parts = explode('**', $line);

        if (count($parts) % 2 === 0) {
            $last = array_pop($parts);
            $parts[count($parts) - 1] .= '**'.$last;
        }

        $runs = [];

        foreach ($parts as $index => $part) {
            if ($part !== '') {
                $runs[] = ['text' => $part, 'bold' => $index % 2 === 1];
            }
        }

        return $runs;
    }
}
