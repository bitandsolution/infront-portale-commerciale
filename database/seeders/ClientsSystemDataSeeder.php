<?php

namespace Database\Seeders;

use App\Models\CommoditySector;
use Illuminate\Database\Seeder;

/**
 * System data of the clients (progettazione-tecnica §5.6.1 A1; D-357): the 24 commodity sectors of
 * M05 §6, with "Industria/Edilizia/Attività" written in UTF-8 (the old system stored an HTML
 * entity) and ALTRO_VARIE asking for a free text (RB-04). Called by SystemDataSeeder.
 *
 * Idempotent like SystemDataSeeder (D-295): sectors are found by code and only the missing ones are
 * created; a sector renamed, reordered or deactivated by the admin is never changed back.
 */
class ClientsSystemDataSeeder extends Seeder
{
    /**
     * code, name, sort order.
     *
     * @var list<array{string, string, int}>
     */
    public const SECTORS = [
        ['ABBIGLIAMENTO', 'Abbigliamento', 10],
        ['ABITAZIONE', 'Abitazione', 20],
        ['ALIMENTARI', 'Alimentari', 30],
        ['AUTOMOBILI', 'Automobili', 40],
        ['BETTING', 'Betting', 50],
        ['BEVANDE_ALCOOLICI', 'Bevande/Alcoolici', 60],
        ['CURA_PERSONA', 'Cura persona', 70],
        ['DISTRIBUZIONE', 'Distribuzione', 80],
        ['ELETTRODOMESTICI', 'Elettrodomestici', 90],
        ['ENTI_ISTITUZIONI', 'Enti/Istituzioni', 100],
        ['FARMACEUTICI_SANITARI', 'Farmaceutici/Sanitari', 110],
        ['FINANZA_ASSICURAZIONI', 'Finanza/Assicurazioni', 120],
        ['GESTIONE_CASA', 'Gestione casa', 130],
        ['GIOCHI_ART_SCUOLA', 'Giochi/Art. scuola', 140],
        ['INDUSTRIA_EDILIZIA_ATTIVITA', 'Industria/Edilizia/Attività', 150],
        ['INFORMATICA', 'Informatica', 160],
        ['MEDIA_EDITORIA', 'Media/Editoria', 170],
        ['MOTO_VEICOLI', 'Moto/Veicoli', 180],
        ['OGGETTI_PERSONALI', 'Oggetti personali', 190],
        ['SERVIZI_PROFESSIONALI', 'Servizi professionali', 200],
        ['TEMPO_LIBERO', 'Tempo libero', 210],
        ['TOILETRIES', 'Toiletries', 220],
        ['TURISMO_VIAGGI', 'Turismo viaggi', 230],
        [CommoditySector::OTHER, 'Altro/Varie', 240],
    ];

    public function run(): void
    {
        $created = 0;

        foreach (self::SECTORS as [$code, $name, $sortOrder]) {
            $sector = CommoditySector::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'requires_free_text' => $code === CommoditySector::OTHER,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);

            if ($sector->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command?->line(sprintf(
            'Commodity sectors: %d created, %d already present (left unchanged).',
            $created,
            count(self::SECTORS) - $created,
        ));
    }
}
