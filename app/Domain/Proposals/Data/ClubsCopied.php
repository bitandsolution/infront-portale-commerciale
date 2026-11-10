<?php

namespace App\Domain\Proposals\Data;

/**
 * Outcome of "Copia su altri club" (M06 §5, M06-CA7; F4-05): the new lock version of the draft, the
 * clubs that received the configuration and, club by club, the options that were skipped with the
 * reason (not available, sold out, beyond the residual, no home match in the period); whether the
 * draft changed at all (false when the targets already had the resulting configuration).
 */
final readonly class ClubsCopied
{
    /**
     * @param  list<string>  $copied  names of the clubs that received the configuration
     * @param  list<array{club: string, items: list<string>}>  $skipped
     */
    public function __construct(
        public int $lockVersion,
        public array $copied,
        public array $skipped,
        public bool $changed,
    ) {}
}
