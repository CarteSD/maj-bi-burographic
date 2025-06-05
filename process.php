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
session_start();

// Vérifier si des lignes ont été sélectionnées
if (!isset($_POST['selected']) || !is_array($_POST['selected']) || empty($_POST['selected'])) {
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Aucune ligne sélectionnée.";
    header('Location: index.php');
    exit;
}

// Vérifier quelle action a été demandée
if (!isset($_POST['action']) || $_POST['action'] !== 'update') {
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Action non reconnue.";
    header('Location: index.php');
    exit;
}

$action = $_POST['action'];
$selectedLines = $_POST['selected'] ?? [];
var_dump($selectedLines); // Pour débogage, à supprimer en production
exit;

try {
    $dbBatigest = Db::getInstance('batigest');
    
    $nbLignesTraitees = 0;
    
    foreach ($selectedLines as $line) {
        $parts = explode('|', $line);
        if (count($parts) < 4) continue;
        
        $codeDoc = $parts[0];
        $numLig = $parts[1];
        $codeElem = $parts[2];
        $qte = (int)$parts[3];
        
        if ($action === 'update') {
            // Vérifier le stock disponible dans Batigest
            $stockDispo = $dbBatigest->query("SELECT (QttAppro - QttConso) AS QttStock FROM ElementStock WHERE CodeElem = :codeElem", ['codeElem' => $codeElem])->fetch(PDO::FETCH_ASSOC)["QttStock"];

            if ($stockDispo && $stockDispo >= $qte) {
                // Mettre à jour le stock dans Batigest
                $typeMvt = 'S';
                $provenance = 'I';
                $pa = round($dbBatigest->query("SELECT PA FROM ElementDef WHERE Code = :codeElem", ['codeElem' => $codeElem])->fetch(PDO::FETCH_ASSOC)["PA"], 2);
                $info = $codeDoc;

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

            }
            else {
                $libelle = $dbBatigest->query("SELECT LibelleStd FROM ElementDef WHERE Code = :codeElem", ['codeElem' => $codeElem])->fetch(PDO::FETCH_ASSOC)["LibelleStd"];
                
                $lignesEchouees[] = [
                    'CodeDoc' => $codeDoc,
                    'NumLig' => $numLig,
                    'CodeElem' => $codeElem,
                    'Libelle' => $libelle,
                    'QteRequis' => $qte,
                    'QteDispo' => $stockDispo
                ];
            }
        }
        
        $nbLignesTraitees++;
    }
    
    if (empty($lignesEchouees)) {
        $_SESSION['success_message'] = true;
        if ($action === 'ignore') {
            $_SESSION['message_details'] = "$nbLignesTraitees ligne(s) ignorée(s) avec succès.";
        } else {
            $_SESSION['message_details'] = "$nbLignesTraitees ligne(s) mise(s) à jour avec succès.";
        }
    } else {
        // Il y a des lignes qui n'ont pas pu être traitées à cause du stock insuffisant
        $_SESSION['success_message'] = false;
        
        // Construire le message d'erreur détaillé
        $message = "$nbLignesTraitees ligne(s) traitée(s).<br><br>" . count($lignesEchouees) . " ligne(s) n'ont pas pu être mise(s) à jour à cause d'un stock insuffisant:<br>";
        
        foreach ($lignesEchouees as $ligne) {
            $message .= "<p><strong>{$ligne['CodeDoc']} - {$ligne['Libelle']}</strong> : " .
                       "Stock requis: {$ligne['QteRequis']}, " .
                       "Stock disponible: {$ligne['QteDispo']}</p>";
        }
        
        $_SESSION['message_details'] = $message;
    }
    
} catch (Exception $e) {
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Erreur lors du traitement : " . $e->getMessage();
}

header('Location: index.php');
exit;