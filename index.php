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

$dbBatigest = Db::getInstance('batigest');

// Récupérer toutes les lignes d'intervention réalisées (R) et non facturées (N)
$toutesLesLignes = $dbBatigest->query(
    "SELECT IntervLigne.CodeDoc, IntervLigne.NumLig, IntervLigne.CodeElem, IntervLigne.Qte
    FROM IntervLigne
    JOIN Interv ON IntervLigne.CodeDoc = Interv.Code
    WHERE IntervLigne.TypeLigne = 'A'
    AND Interv.Etat = 'R'
    AND Interv.EtatFact = 'N'
    ORDER BY Interv.Code ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$lignesAMettreAJour = [];
// Parcourir toutes les lignes d'intervention afin de vérifier les mouvements de stock
foreach ($toutesLesLignes as $line) {

    // Vérification de l'existence d'un mouvement de stock de sortie pour cette ligne
    $sortieMvt = $dbBatigest->query(
        "SELECT COUNT(*) AS nb
        FROM ElementMvtStock
        WHERE TypeMvt = 'S'
        AND CodeElem = :codeElem
        AND Quantite = :qte
        AND Info = :info", 
        [
            'codeElem' => $line['CodeElem'],
            'qte' => $line['Qte'],
            'info' => $line['CodeDoc']
        ]
    )->fetch(PDO::FETCH_ASSOC);

    // Si un mouvement de stock de sortie existe pour cette ligne, on ne l'ajoute pas à la liste des lignes à mettre à jour
    if ($sortieMvt['nb'] > 0) {
        continue;
    }

    // Si aucun mouvement de stock de sortie n'existe, on ajoute la ligne à mettre à jour
    $lignesAMettreAJour[] = [
        'CodeDoc' => $line['CodeDoc'],
        'NumLig' => $line['NumLig'],
        'CodeElem' => $line['CodeElem'],
        'Qte' => $line['Qte'],
    ];
}

$lignesGroupees = [];
// Parcourir les lignes à mettre à jour et les regrouper par CodeDoc
foreach ($lignesAMettreAJour as $ligne) {
    $codeDoc = $ligne['CodeDoc'];

    // Vérifier s'il existe déjà une clé pour ce CodeDoc
    if (!isset($lignesGroupees[$codeDoc])) {
        $lignesGroupees[$codeDoc] = [];
    }

    // Ajouter la ligne au groupe correspondant
    $lignesGroupees[$codeDoc][] = $ligne;
}

// Générer le HTML pour afficher le tableau des lignes à mettre à jour
$html = '<form action="process.php" method="post" id="process-form">
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

// Remplacer le nombre de BI dans le template
$template = str_replace('{{nb_bi}}', count($lignesAMettreAJour), $template);

// Si aucune ligne à mettre à jour afficher un message
if (empty($lignesAMettreAJour)) {
    $html .= '<tr><td colspan="5" style="text-align: center;">Aucune intervention à mettre à jour</td></tr>';
} else {
    // Parcourir chaque groupe de BI
    foreach ($lignesGroupees as $codeDoc => $lignes) {
        $nbLignes = count($lignes);
        
        // Première ligne du groupe différente des autres car elle contient les checkboxes
        $premiereLigne = $lignes[0];
        $html .= '<tr onclick="selectBIGroup(\'' . htmlspecialchars($codeDoc) . '\')">';
        
        // Case à cocher avec rowspan pour prendre toute la hauteur du groupe
        $html .= '<td rowspan="' . $nbLignes . '" style="vertical-align: middle; text-align: center;">
            <input type="checkbox" class="bi-checkbox" data-bi="' . htmlspecialchars($codeDoc) . '" onchange="toggleBIGroup(\'' . htmlspecialchars($codeDoc) . '\')">';
        
        // Ajouter les checkboxes cachées pour chaque ligne du groupe (nécessaire pour le traitement)
        foreach ($lignes as $ligne) {
            $html .= '<input type="checkbox" class="line-checkbox hidden-checkbox" data-bi="' . htmlspecialchars($codeDoc) . '" name="selected[]" value="' . htmlspecialchars($ligne['CodeDoc'] . '|' . $ligne['NumLig'] . '|' . $ligne['CodeElem'] . '|' . $ligne['Qte']) . '" style="display: none;">';
        }
        
        $html .= '</td>';
        
        // BI avec rowspan pour prendre toute la hauteur du groupe
        $html .= '<td rowspan="' . $nbLignes . '" style="vertical-align: middle;">' . htmlspecialchars($codeDoc) . '</td>';
        
        // Détails de la première ligne

        // Récupérer le libellé de l'élément
        $result = $dbBatigest->query(
            "SELECT LibelleStd
            FROM ElementDef
            WHERE Code = :code",
            [
                "code" => $premiereLigne["CodeElem"]
            ]
        )->fetch();

        $libelle = $result ? $result["LibelleStd"] : "Libellé introuvable";

        // Afficher le libellé, le code élément et la quantité
        $html .= '<td>' . htmlspecialchars($libelle) . '</td>';
        $html .= '<td>' . htmlspecialchars($premiereLigne['CodeElem']) . '</td>';
        $html .= '<td>' . htmlspecialchars((int)$premiereLigne['Qte']) . '</td>';
        $html .= '</tr>';
        
        // Lignes suivantes du groupe (sans les colonnes avec rowspan)
        for ($i = 1; $i < $nbLignes; $i++) {
            $ligne = $lignes[$i];
            $html .= '<tr onclick="selectBIGroup(\'' . htmlspecialchars($codeDoc) . '\')">';
            
            // Récupération du libellé de l'élément
            $result = $dbBatigest->query(
                "SELECT LibelleStd
                FROM ElementDef
                WHERE Code = :code",
                [
                    "code" => $ligne["CodeElem"]
                ]
            )->fetch();

            $libelle = $result ? $result["LibelleStd"] : "Libellé introuvable";

            // Afficher le libellé, le code élément et la quantité
            $html .= '<td>' . htmlspecialchars($libelle) . '</td>';
            $html .= '<td>' . htmlspecialchars($ligne['CodeElem']) . '</td>';
            $html .= '<td>' . htmlspecialchars((int)$ligne['Qte']) . '</td>';
            $html .= '</tr>';
        }
    }
}

$html .= '</tbody></table>';

// Ajouter les boutons de sélection et de mise à jour
$html .= '<div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
    <button type="button" class="select-all-btn" onclick="selectAllLines()"' . (empty($lignesAMettreAJour) ? ' disabled' : '') . '>
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 2v6M2 5h6m4 0h3m-3 17h3m3-17h.5A3.5 3.5 0 0 1 22 8.5V9m0 9v.5a3.5 3.5 0 0 1-3.5 3.5H18m-9 0h-.5A3.5 3.5 0 0 1 5 18.5V18m17-6v3M5 12v3" color="currentColor"/></svg>
        Sélectionner tout
    </button>
    <button type="submit" name="action" value="update" class="update-selected-btn"' . (empty($lignesAMettreAJour) ? ' disabled' : '') . '>
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 21q-1.875 0-3.512-.712t-2.85-1.925t-1.925-2.85T3 12t.713-3.512t1.924-2.85t2.85-1.925T12 3q2.05 0 3.888.875T19 6.35V5q0-.425.288-.712T20 4t.713.288T21 5v4q0 .425-.288.713T20 10h-4q-.425 0-.712-.288T15 9t.288-.712T16 8h1.75q-1.025-1.4-2.525-2.2T12 5Q9.075 5 7.038 7.038T5 12t2.038 4.963T12 19q2.375 0 4.25-1.425t2.475-3.675q.125-.4.45-.6t.725-.15q.425.05.675.362t.15.688q-.725 2.975-3.15 4.888T12 21m1-9.4l2.5 2.5q.275.275.275.7t-.275.7t-.7.275t-.7-.275l-2.8-2.8q-.15-.15-.225-.337T11 11.975V8q0-.425.288-.712T12 7t.713.288T13 8z"/></svg>
        Mettre à jour la sélection
    </button>
</div>';

$html .= '</form>';

// Remplacer le placeholder {{interventions}} dans le template par le HTML généré
$template = str_replace('{{interventions}}', $html, $template);

// Gérer les messages de succès ou d'erreur enregistrés dans la session
session_start();

if (isset($_SESSION['success_message'])) {
    // Si le message de succès est défini, on l'affiche
    if ($_SESSION['success_message'] === true) {
        $template = str_replace(
            '{{success}}',
            '<div class="alert alert-success">
                <i class="fa fa-check-circle"></i>
                <span>' . (isset($_SESSION['message_details']) ? $_SESSION['message_details'] : 'Mise à jour réussie !') . '</span>
            </div>',
            $template
        );
    }
    // Sinon, on affiche un message d'erreur
    else {
        $template = str_replace(
            '{{success}}',
            '<div class="alert alert-error">
                <i class="fa fa-exclamation-triangle"></i>
                <span>' . (isset($_SESSION['message_details']) ? $_SESSION['message_details'] : 'Erreur lors de la mise à jour.') . '</span>
            </div>',
            $template
        );
    }
    // Nettoyer la session après affichage du message
    unset($_SESSION['success_message']);
    if (isset($_SESSION['message_details'])) {
        unset($_SESSION['message_details']);
    }
} else {
    // Si aucun message n'est défini, on supprime le placeholder {{success}} du template
    $template = str_replace('{{success}}', '', $template);
}

// Afficher le template final
echo $template;
return;