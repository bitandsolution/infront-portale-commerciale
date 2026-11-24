<?php

namespace App\Enums;

/**
 * Group of a KPI (M08 §5; D-108, D-203): the KPI of the club (fanbase, fans, attendance, audience,
 * broadcasts, season tickets) and the digital ones (followers, digital users, DEM contacts). The
 * page of a club shows one section per group, in this order.
 */
enum KpiGroup: string
{
    case Club = 'club';
    case Digital = 'digital';

    /**
     * Italian label (lang/it/content.php).
     */
    public function label(): string
    {
        $label = __('content.kpi_groups.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
