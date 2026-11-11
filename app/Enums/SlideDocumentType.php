<?php

namespace App\Enums;

/**
 * Document type of a slide (M08 §4.1): network, club or both.
 */
enum SlideDocumentType: string
{
    case Network = 'network';
    case Club = 'club';
    case Both = 'both';

    public function fits(DocumentType $type): bool
    {
        return $this === self::Both || $this->value === $type->value;
    }

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.document_types.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
