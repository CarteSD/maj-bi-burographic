<?php
require_once 'Db.php';

$template = file_get_contents('index.template.html');

$dbInterventions = Db::getInstance('interventions');
$dbBatigest = Db::getInstance('batigest');

$lignesMisesAJour = $dbInterventions->query('SELECT * FROM HistoMaj')->fetchAll(PDO::FETCH_ASSOC);
$toutesLesLignes = $dbBatigest->query("SELECT IntervLigne.CodeDoc, IntervLigne.NumLig, IntervLigne.CodeElem, IntervLigne.Qte FROM IntervLigne JOIN Interv ON IntervLigne.CodeDoc = Interv.Code WHERE IntervLigne.TypeLigne = 'A' AND Interv.Etat = 'R' AND Interv.EtatFact = 'N'")->fetchAll(PDO::FETCH_ASSOC);

$lignesAMettreAJour = [];
$majExistantes = [];
foreach ($lignesMisesAJour as $maj) {
    $key = $maj['CodeDoc'].'|'.$maj['NumLig'];
    $majExistantes[$key] = true;
}
foreach ($toutesLesLignes as $line) {
    $key = $line['CodeDoc'].'|'.$line['NumLig'];
    if (!isset($majExistantes[$key])) {
        $lignesAMettreAJour[] = [
            'CodeDoc' => $line['CodeDoc'],
            'NumLig' => $line['NumLig'],
            'CodeElem' => $line['CodeElem'],
            'Qte' => $line['Qte'],
        ];
    }
}

$html = '<table border="1" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>Bon d\'intervention</th>
            <th>Libellé</th>
            <th>Code Élément</th>
            <th>Quantité</th>
        </tr>
    </thead>
    <tbody>';

$template = str_replace('{{nb_bi}}', count($lignesAMettreAJour), $template);

// Si aucune ligne à mettre à jour
if (empty($lignesAMettreAJour)) {
    $html .= '<tr><td colspan="5" style="text-align: center;">Aucune intervention à mettre à jour</td></tr>';
} else {
    // Ajouter chaque ligne à mettre à jour
    foreach ($lignesAMettreAJour as $line) {
        $html .= '<tr>
            <td>' . htmlspecialchars($line['CodeDoc']) . '</td>
            <td>' . htmlspecialchars($dbBatigest->query("SELECT LibelleStd FROM ElementDef WHERE Code = :code", ["code" => $line["CodeElem"]])->fetch()["LibelleStd"]) . '</td>
            <td>' . htmlspecialchars($line['CodeElem']) . '</td>
            <td>' . htmlspecialchars((int)$line['Qte']) . '</td>
        </tr>';
    }
}

$html .= '</tbody></table>';

$template = str_replace('{{interventions}}', $html, $template);

session_start();

if (isset($_SESSION['success_message'])) {
    if ($_SESSION['success_message'] === true) {
        $template = str_replace(
            '{{success}}',
            '<div class="alert alert-success">
                <i class="fa fa-check-circle"></i>
                <span>' . (isset($_SESSION['message_details']) ? htmlspecialchars($_SESSION['message_details']) : 'Mise à jour réussie !') . '</span>
            </div>',
            $template
        );
    } else {
        $template = str_replace(
            '{{success}}',
            '<div class="alert alert-error">
                <i class="fa fa-exclamation-triangle"></i>
                <span>' . (isset($_SESSION['message_details']) ? htmlspecialchars($_SESSION['message_details']) : 'Erreur lors de la mise à jour.') . '</span>
            </div>',
            $template
        );
    }
    unset($_SESSION['success_message']);
    if (isset($_SESSION['message_details'])) {
        unset($_SESSION['message_details']);
    }
} else {
    $template = str_replace('{{success}}', '', $template);
}

echo $template;
return;