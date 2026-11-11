<?php

namespace App\Enums;

/**
 * Type of a document (D-121): club with one club, network with two or more. For a group of variants
 * the distinct clubs of all the variants count (D-403): A Lazio and B Roma make a network document.
 */
enum DocumentType: string
{
    case Club = 'club';
    case Network = 'network';

    /**
     * Type for a number of distinct clubs, null without clubs.
     */
    public static function forClubs(int $distinctClubs): ?self
    {
        return match (true) {
            $distinctClubs <= 0 => null,
            $distinctClubs === 1 => self::Club,
            default => self::Network,
        };
    }

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.document_kinds.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
