<?php
/**
 * @file     log.php
 * @author   Estéban DESESSARD
 * @brief    Fichier pour afficher le journal des interventions traitées
 * @details
 * @date     28/05/2025
 * @version  0.0
 */

require_once 'Db.php';

$template = file_get_contents('log.template.html');

$dbInterventions = Db::getInstance('interventions');
$dbBatigest = Db::getInstance('batigest');

// Récupérer toutes les entrées du journal
$journalEntries = $dbInterventions->query('SELECT * FROM HistoMaj ORDER BY DateMaj DESC')->fetchAll(PDO::FETCH_ASSOC);

// Construire le tableau HTML
$html = '<table border="1" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>Bon d\'intervention</th>
            <th>Libellé</th>
            <th>Code Élément</th>
            <th>Quantité</th>
            <th>Date de traitement</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>';

$template = str_replace('{{nb_records}}', count($journalEntries), $template);

// Si aucune entrée dans le journal
if (empty($journalEntries)) {
    $html .= '<tr><td colspan="6" class="empty-message">Aucune intervention dans le journal</td></tr>';
} else {
    // Ajouter chaque entrée au tableau
    foreach ($journalEntries as $entry) {
        // Récupérer le libellé de l'élément
        $libelle = $dbBatigest->query(
            "SELECT LibelleStd FROM ElementDef WHERE Code = :code",
            ["code" => $entry["CodeElem"]]
        )->fetch(PDO::FETCH_ASSOC);
        
        $libelle = $libelle ? $libelle["LibelleStd"] : "Non trouvé";
        
        // Formater la date
        $date = new DateTime($entry['DateMaj']);
        $dateFormatee = $date->format('d/m/Y');
        
        // Déterminer le statut
        $statut = isset($entry['Ignored']) && $entry['Ignored'] == 1 
            ? '<span style="color: var(--rose);">Ignoré</span>' 
            : '<span style="color: var(--bleu);">Traité</span>';
        
        $html .= '<tr>
            <td>' . htmlspecialchars($entry['CodeDoc']) . '</td>
            <td>' . htmlspecialchars($libelle) . '</td>
            <td>' . htmlspecialchars($entry['CodeElem']) . '</td>
            <td>' . htmlspecialchars((int)$entry['Qte']) . '</td>
            <td>' . $dateFormatee . '</td>
            <td>' . $statut . '</td>
        </tr>';
    }
}

$html .= '</tbody></table>';

$template = str_replace('{{log_table}}', $html, $template);

echo $template;
return;