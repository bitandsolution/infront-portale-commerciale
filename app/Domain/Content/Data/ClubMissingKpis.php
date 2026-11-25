<?php

namespace App\Domain\Content\Data;

use App\Support\Text;

/**
 * A club of a document without some of the KPI its document type uses (M08 RB-05, §10; D-502): all
 * the active KPI for the club document, the aggregated ones for the network document. The warning
 * never blocks the document (D-503).
 */
final readonly class ClubMissingKpis
{
    /**
     * @param  list<string>  $missingNames  names of the missing KPI, in the order of the definitions
     */
    public function __construct(
        public int $clubId,
        public string $clubName,
        public array $missingNames,
        public int $used,
    ) {}

    /** "SS Lazio: mancano 12 KPI su 13 (Fanbase Italia, …)". */
    public function warning(): string
    {
        $missing = count($this->missingNames);

        return Text::get($missing === 1 ? 'content.composition.missing_kpi_one' : 'content.composition.missing_kpis', [
            'club' => $this->clubName,
            'missing' => (string) $missing,
            'total' => (string) $this->used,
            'names' => implode(', ', $this->missingNames),
        ]);
    }
}
