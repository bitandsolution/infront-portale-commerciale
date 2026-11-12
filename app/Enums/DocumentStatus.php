<?php

namespace App\Enums;

/**
 * Status of a document (progettazione-tecnica §5.3.5, §5.5; DM-04): queued after the approval or a
 * regeneration, generating while the job of the queue "documents" works on it, ready with its PDF,
 * failed after the last attempt or when the library lacks a mandatory slide (D-416).
 */
enum DocumentStatus: string
{
    case Queued = 'queued';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Generating;
    }

    /**
     * Italian label (lang/it/documents.php).
     */
    public function label(): string
    {
        $label = __('documents.statuses.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
