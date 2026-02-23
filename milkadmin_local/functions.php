<?php
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * This file is left empty; it's useful in case you need to insert custom code that runs before the modules are loaded. It's loaded first in Get::loadModules();
 */


class StaticData {
    static function Days() {
        return  ['0' => 'Domenica', '1' => 'Lunedì', '2' => 'Martedì', '3' => 'Mercoledì', '4' => 'Giovedì', '5' => 'Venerdì', '6' => 'Sabato'];
    } 
    
    static function Months($empty = false) {
        $month =  [
                   
                    'Gen' => 'Gennaio',
                    'Feb' => 'Febbraio',
                    'Mar' => 'Marzo',
                    'Apr' => 'Aprile',
                    'Mag' => 'Maggio',
                    'Giu' => 'Giugno',
                    'Lug' => 'Luglio',
                    'Ago' => 'Agosto',
                    'Set' => 'Settembre',
                    'Ott' => 'Ottobre',
                    'Nov' => 'Novembre',
                    'Dic' => 'Dicembre'
        ];
        if ($empty) {
            $month = ['' => 'Seleziona...'] + $month;
        }
        return $month;
    }

    static function PosWorks() {
        return ['1' => 'Occupato a tempo pieno', '2' => 'Occupato part time', '3' => 'Lavori occasionali', '4' => 'Casalinga', '5' => 'Studente', '6' => 'Pensionato'];
    }

    static function TipoPrenotazioni() {
        return [
            'C' => 'Accesso al Corso',
            'B' => 'Cambio Insegnante',
            'A' => 'Cambio Orario',
        ];
    }

    /**
     * @TODO verificare se c'è un modo migliore per ottenere l'anno corrente
     */
    static function getCurrentYear() {
        return '25/26';
    }

    static function getRateMensili() {
        return [
            'RATA1' => ['mese' => 10, 'label' => 'Ottobre', 'scadenza_giorno' => 15],
            'RATA2' => ['mese' => 11, 'label' => 'Novembre', 'scadenza_giorno' => 15],
            'RATA3' => ['mese' => 12, 'label' => 'Dicembre', 'scadenza_giorno' => 15],
            'RATA4' => ['mese' => 1, 'label' => 'Gennaio', 'scadenza_giorno' => 15],
            'RATA5' => ['mese' => 2, 'label' => 'Febbraio', 'scadenza_giorno' => 15],
            'RATA6' => ['mese' => 3, 'label' => 'Marzo', 'scadenza_giorno' => 15],
            'RATA7' => ['mese' => 4, 'label' => 'Aprile', 'scadenza_giorno' => 15],
            'RATA8' => ['mese' => 5, 'label' => 'Maggio', 'scadenza_giorno' => 15],
            'RATA9' => ['mese' => 6, 'label' => 'Giugno', 'scadenza_giorno' => 15],
        ];
    }

    public static function getRataMonthNumber(string $rataField): ?int
    {
        $rataField = strtoupper($rataField);
        $rateMap = \StaticData::getRateMensili();
        if (isset($rateMap[$rataField]['mese'])) {
            return (int)$rateMap[$rataField]['mese'];
            }
            return null;
        }

    public static function getRataIndex(string $rataField): ?int
    {
        if (preg_match('/^RATA(\d+)$/', strtoupper($rataField), $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    public static function daysOfBlocking(): int {
        return 5;
    }


    public static function causaliRitiro(): array {
        return [        
            'C.R.' => 'Ritiro',
            'C.O.' => 'Cambio Orario',
            'C.L.' => 'Lascia',
            'C.I.' => 'Cambio Insegnante',
        ];
    }
    

}

if (!class_exists('SociRateRules')) {
    class SociRateRules
    {
     
        public static function getPosticipoMoraRecord(int $socioId, string $rataField, string $annoScolastico): ?array
        {
            $socioId = (int)$socioId;
            if ($socioId <= 0) {
                return null;
            }

            $meseNumero = \StaticData::getRataMonthNumber($rataField);
            $indiceRata = \StaticData::getRataIndex($rataField);
            if ($meseNumero === null && $indiceRata === null) {
                return null;
            }

            $db = (new \Local\Modules\Config\ConfigModel())->getDb();
            if (!$db) {
                return null;
            }

            $row = null;
            $whereParts = [];
            $params = [$socioId];

            if ($meseNumero !== null) {
                $whereParts[] = 'MESE = ?';
                $params[] = $meseNumero;
            }

            if ($indiceRata !== null && $indiceRata !== $meseNumero) {
                $whereParts[] = 'MESE = ?';
                $params[] = $indiceRata;
            }

            if ($whereParts) {
                $sql = 'SELECT ID_MOROSO, DATA_SCADENZA FROM posticipo_mora WHERE MATRIC = ? AND (' . implode(' OR ', $whereParts) . ')'
                    . ' ORDER BY DATA_SCADENZA DESC, ID_MOROSO DESC LIMIT 1';
                $row = $db->getRow($sql, $params);
            }

            if (!$row && $meseNumero !== null && (int)date('n') === $meseNumero) {
                $row = $db->getRow(
                    'SELECT ID_MOROSO, DATA_SCADENZA FROM posticipo_mora WHERE MATRIC = ? AND (MESE = 0 OR MESE IS NULL)'
                    . ' ORDER BY DATA_SCADENZA DESC, ID_MOROSO DESC LIMIT 1',
                    [$socioId]
                );
            }

            if (is_array($row)) {
                return [
                    'id' => $row['ID_MOROSO'] ?? null,
                    'data' => $row['DATA_SCADENZA'] ?? null,
                ];
            }

            if (is_object($row)) {
                return [
                    'id' => $row->ID_MOROSO ?? null,
                    'data' => $row->DATA_SCADENZA ?? null,
                ];
            }

            return null;
        }
       
    }
}

/*
function milk_get_orari_richiesti(): array
{
    $orari = \App\Settings::get('orari_richiesti', 'prenotazioni');
    return is_array($orari) ? array_values($orari) : [];
}

function milk_get_orari_richiesti_giorni_options(): array
{
    return [
        '' => 'Qualsiasi giorno',
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
        7 => 'Domenica',
    ];
}

function milk_format_orario_richiesto(array $row): string
{
    $giorni = milk_get_orari_richiesti_giorni_options();
    $giorno_key = $row['giorno'] ?? '';
    $giorno = $giorni[$giorno_key] ?? 'Qualsiasi giorno';
    $ora_dal = $row['ora_dal'] ?? '';
    $ora_al = $row['ora_al'] ?? '';
    return trim($giorno . ' ' . $ora_dal . '-' . $ora_al);
}

function milk_build_orari_richiesti_list_html(array $orari, ?string $page = null, string $action = 'orari-form'): string
{
    if (empty($orari)) {
        return '<p class="text-muted mb-0">Nessun orario configurato</p>';
    }

    usort($orari, function (array $a, array $b): int {
        $giorno_a = (int)($a['giorno'] ?? 0);
        $giorno_b = (int)($b['giorno'] ?? 0);
        if ($giorno_a !== $giorno_b) {
            return $giorno_a <=> $giorno_b;
        }
        return strcmp((string)($a['ora_dal'] ?? ''), (string)($b['ora_dal'] ?? ''));
    });

    $html = '<div class="list-group">';
    foreach ($orari as $row) {
        $id = (int)($row['id'] ?? 0);
        $label = milk_format_orario_richiesto($row);
        $edit_link = null;
        if ($page) {
            $edit_link = '?page=' . $page . '&action=' . $action . '&id=' . $id;
        }

        $html .= '<div class="list-group-item d-flex justify-content-between align-items-center">'
            . '<div><i class="bi bi-clock me-2"></i>' . htmlspecialchars($label) . '</div>';

        if ($edit_link) {
            $html .= '<div class="btn btn-sm btn-outline-primary" role="button" tabindex="0" data-fetch="post" data-url="' . htmlspecialchars($edit_link) . '">'
                . '<i class="bi bi-pencil"></i> Modifica</div>';
        }

        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}
*/
