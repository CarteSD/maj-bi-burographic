<?php
/**
 * @file     index.php
 * @author   Estéban DESESSARD
 * @brief    Fichier principal pour afficher les interventions à mettre à jour
 * @details
 * @date     15/05/2025
 * @version  0.0
 */


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

$html = '<form action="ignore.php" method="post" id="ignore-form">
<table border="1" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>Sélectionner</th>
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
            <td><input type="checkbox" class="line-checkbox" name="ignore[]" value="' . htmlspecialchars($line['CodeDoc'] . '|' . $line['NumLig']) . '|' . $line['CodeElem'] . '|' . $line['Qte'] . '"></td>
            <td>' . htmlspecialchars($line['CodeDoc']) . '</td>
            <td>' . htmlspecialchars($dbBatigest->query("SELECT LibelleStd FROM ElementDef WHERE Code = :code", ["code" => $line["CodeElem"]])->fetch()["LibelleStd"]) . '</td>
            <td>' . htmlspecialchars($line['CodeElem']) . '</td>
            <td>' . htmlspecialchars((int)$line['Qte']) . '</td>
        </tr>';
    }
}

$html .= '</tbody></table>';

// Ajouter les boutons d'action si des lignes sont présentes
if (!empty($lignesAMettreAJour)) {
    $html .= '<div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
        <button type="submit" class="ignore-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12L19 6.41z"/></svg>
            Ignorer la sélection
        </button>
        <button type="button" onclick="document.getElementById(\'update-form\').submit();" class="update-all-btn" style="text-decoration: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 21q-1.875 0-3.512-.712t-2.85-1.925t-1.925-2.85T3 12t.713-3.512t1.924-2.85t2.85-1.925T12 3q2.05 0 3.888.875T19 6.35V5q0-.425.288-.712T20 4t.713.288T21 5v4q0 .425-.288.713T20 10h-4q-.425 0-.712-.288T15 9t.288-.712T16 8h1.75q-1.025-1.4-2.525-2.2T12 5Q9.075 5 7.038 7.038T5 12t2.038 4.963T12 19q2.375 0 4.25-1.425t2.475-3.675q.125-.4.45-.6t.725-.15q.425.05.675.362t.15.688q-.725 2.975-3.15 4.888T12 21m1-9.4l2.5 2.5q.275.275.275.7t-.275.7t-.7.275t-.7-.275l-2.8-2.8q-.15-.15-.225-.337T11 11.975V8q0-.425.288-.712T12 7t.713.288T13 8z"/></svg>
            Tout mettre à jour
        </a>
    </div>';
}

$html .= '</form>';

// Ajouter un formulaire caché pour la mise à jour complète
$html .= '<form action="maj.php" method="post" id="update-form" style="display:none;">
    <input type="hidden" name="update_all" value="1">
</form>';


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