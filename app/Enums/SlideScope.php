<?php

namespace App\Enums;

/**
 * Scope of a slide (M08 §4.1; D-202): Infront slides make the network document, the slides of a club
 * the document of that club (M09 §3, D-403).
 */
enum SlideScope: string
{
    case Infront = 'infront';
    case Club = 'club';

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.scopes.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
