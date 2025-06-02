<?php
/**
 * @file     process.php
 * @author   Estéban DESESSARD
 * @brief    Fichier de traitement des formulaires pour ignorer ou mettre à jour des interventions
 * @details
 * @date     02/06/2025
 * @version  0.0
 */

require_once 'Db.php';

// Vérifier si des lignes ont été sélectionnées
if (!isset($_POST['ignore']) || !is_array($_POST['ignore']) || empty($_POST['ignore'])) {
    session_start();
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Aucune ligne sélectionnée.";
    header('Location: index.php');
    exit;
}

// Vérifier quelle action a été demandée
if (!isset($_POST['action']) || ($_POST['action'] !== 'ignore' && $_POST['action'] !== 'update')) {
    session_start();
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Action non reconnue.";
    header('Location: index.php');
    exit;
}

session_start();

$action = $_POST['action'];
$selectedLines = $_POST['ignore'] ?? [];

try {
    $dbInterventions = Db::getInstance('interventions');
    $dbBatigest = Db::getInstance('batigest');
    
    $nbLignesTraitees = 0;
    
    foreach ($selectedLines as $line) {
        $parts = explode('|', $line);
        if (count($parts) < 4) continue;
        
        $codeDoc = $parts[0];
        $numLig = $parts[1];
        $codeElem = $parts[2];
        $qte = (int)$parts[3];
        
        if ($action === 'ignore') {
            // Insérer dans la table HistoMaj comme ignoré
            $dbInterventions->query("INSERT INTO HistoMaj VALUES (:codeDoc, :numLig, :codeElem, :qte, 1, GETDATE())", [
                'codeDoc' => $codeDoc,
                'numLig' => $numLig,
                'codeElem' => $codeElem,
                'qte' => $qte
            ]);
        } 
        else if ($action === 'update') {
            // Mettre à jour le stock dans Batigest
            $typeMvt = 'S';
            $provenance = 'I';
            $pa = round($dbBatigest->query("SELECT PA FROM ElementDef WHERE Code = :codeElem", ['codeElem' => $codeElem])->fetch(PDO::FETCH_ASSOC)["PA"], 2);
            $info = "Bon Intervention [". $codeDoc . "]";

            $dbBatigest->query("INSERT INTO ElementMvtStock (CodeElem, TypeMvt, Provenance, Date, Quantite, PA, Info, Suivi, TypeOrigine, Origine, Destination) 
                        VALUES (:codeElem, :typeMvt, :provenance, GETDATE(), :quantite, :pa, :info, 0, '', '', '')", [
                'codeElem' => $codeElem,
                'typeMvt' => $typeMvt,
                'provenance' => $provenance,
                'quantite' => $qte,
                'pa' => $pa,
                'info' => $info
            ]);

            $dbBatigest->query("UPDATE ElementStock SET QttConso = QttConso + :quantite WHERE CodeElem = :codeElem", [
                'quantite' => $qte,
                'codeElem' => $codeElem
            ]);

            // Insérer dans la table HistoMaj comme mise à jour
            $dbInterventions->query("INSERT INTO HistoMaj VALUES (:codeDoc, :numLig, :codeElem, :quantite, 0, GETDATE())", [
                'codeDoc' => $codeDoc,
                'numLig' => $numLig,
                'codeElem' => $codeElem,
                'quantite' => $qte
            ]);
        }
        
        $nbLignesTraitees++;
    }
    
    $_SESSION['success_message'] = true;
    if ($action === 'ignore') {
        $_SESSION['message_details'] = "$nbLignesTraitees ligne(s) ignorée(s) avec succès.";
    } else {
        $_SESSION['message_details'] = "$nbLignesTraitees ligne(s) mise(s) à jour avec succès.";
    }
    
} catch (Exception $e) {
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Erreur lors du traitement : " . $e->getMessage();
}

header('Location: index.php');
exit;