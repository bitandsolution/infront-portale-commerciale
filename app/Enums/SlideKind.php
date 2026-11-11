<?php

namespace App\Enums;

/**
 * Kind of a slide (D-116): a static PNG, or a dynamic slide made of a background and a layout whose
 * data the system writes (layout_code, enum DocumentLayout).
 */
enum SlideKind: string
{
    case Static = 'static';
    case Dynamic = 'dynamic';

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.kinds.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
