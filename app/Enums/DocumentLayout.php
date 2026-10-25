<?php

namespace App\Enums;

/**
 * Dynamic layouts of the slides (M08 §4.3, D-116, layout_code of slides). Position and style of the
 * data are defined in code (docs/layout-dinamici.md). Slice S1 builds only the network recap, the
 * most demanding one (D-309); slice F7 adds the other eight layouts.
 */
enum DocumentLayout: string
{
    case NetworkRecap = 'network_recap';

    /**
     * Blade view of the layout (resources/views/documents/layouts).
     */
    public function view(): string
    {
        return match ($this) {
            self::NetworkRecap => 'documents.layouts.network-recap',
        };
    }
}
