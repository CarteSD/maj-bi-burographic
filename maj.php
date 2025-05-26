<?php
/**
 * @file     maj.php
 * @author   Estéban DESESSARD
 * @brief
 * @details
 * @date     15/05/2025
 * @version  0.0
 */

require_once 'Db.php';

// Vérifier si la demande vient du formulaire
if (!isset($_POST['update_all']) || $_POST['update_all'] != 1) {
    header('Location: index.php');
    exit;
}

session_start();

try {
    $dbInterventions = Db::getInstance('interventions');
    $dbBatigest = Db::getInstance('batigest');

    // Récupérer les lignes à mettre à jour (même code que dans index.php)
    $lignesMisesAJour = $dbInterventions->query('SELECT * FROM HistoMaj')->fetchAll(PDO::FETCH_ASSOC);
    $toutesLesLignes = $dbBatigest->query("SELECT CodeDoc, NumLig, CodeElem, Qte FROM IntervLigne WHERE TypeLigne = 'A'")->fetchAll(PDO::FETCH_ASSOC);

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

    $nbLignesTraitees = 0;
    foreach ($lignesAMettreAJour as $line) {
        $codeElem = $line['CodeElem'];
        $typeMvt = 'S';
        $provenance = 'I';
        $quantite = $line['Qte'];
        $pa = round($dbBatigest->query("SELECT PA FROM ElementDef WHERE Code = :codeElem", [$codeElem])->fetch(PDO::FETCH_ASSOC)["PA"], 2);
        $info = "Bon Intervention [". $line['CodeDoc'] . "]";

        $dbBatigest->query("INSERT INTO ElementMvtStock (CodeElem, TypeMvt, Provenance, Date, Quantite, PA, Info, Suivi, TypeOrigine, Origine, Destination)
                       VALUES (:codeElem, :typeMvt, :provenance, GETDATE(), :quantite, :pa, :info, 0, '', '', '')", [
            'codeElem' => $codeElem,
            'typeMvt' => $typeMvt,
            'provenance' => $provenance,
            'quantite' => $quantite,
            'pa' => $pa,
            'info' => $info
        ]);

        $dbBatigest->query("UPDATE ElementStock SET QttConso = QttConso + :quantite WHERE CodeElem = :codeElem", [
            'quantite' => $quantite,
            'codeElem' => $codeElem
        ]);

        $dbInterventions->query("INSERT INTO HistoMaj VALUES (:codeDoc, :numLig, :codeElem, :quantite, GETDATE())", [
            'codeDoc' => $line['CodeDoc'],
            'numLig' => $line['NumLig'],
            'codeElem' => $codeElem,
            'quantite' => (int)$quantite
        ]);
        
        $nbLignesTraitees++;
    }
    
    $_SESSION['success_message'] = true;
    $_SESSION['message_details'] = "Mise à jour réussie. $nbLignesTraitees ligne(s) traitée(s).";
    
} catch (Exception $e) {
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Erreur lors de la mise à jour : " . $e->getMessage();
}

header('Location: index.php');
exit;