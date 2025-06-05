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

try {
    $dbBatigest = Db::getInstance('batigest');
    
    // Regrouper les lignes par BI
    $lignesParBI = [];
    foreach ($selectedLines as $line) {
        $parts = explode('|', $line);
        if (count($parts) < 4) continue;
        
        $codeDoc = $parts[0];
        if (!isset($lignesParBI[$codeDoc])) {
            $lignesParBI[$codeDoc] = [];
        }
        
        $lignesParBI[$codeDoc][] = [
            'codeDoc' => $parts[0],
            'numLig' => $parts[1],
            'codeElem' => $parts[2],
            'qte' => (int)$parts[3]
        ];
    }
    
    $nbLignesTraitees = 0;
    $lignesEchouees = [];
    
    // Traiter chaque BI
    foreach ($lignesParBI as $codeDoc => $lignes) {
        $biValide = true;
        $stocksDisponibles = [];
        
        // 1. Vérifier d'abord toutes les lignes du BI
        foreach ($lignes as $ligne) {
            $stockDispo = $dbBatigest->query(
                "SELECT (QttAppro - QttConso) AS QttStock FROM ElementStock WHERE CodeElem = :codeElem", 
                ['codeElem' => $ligne['codeElem']]
            )->fetch(PDO::FETCH_ASSOC)["QttStock"];
            
            if (!$stockDispo || $stockDispo < $ligne['qte']) {
                $biValide = false;
                $libelle = $dbBatigest->query(
                    "SELECT LibelleStd FROM ElementDef WHERE Code = :codeElem",
                    ['codeElem' => $ligne['codeElem']]
                )->fetch(PDO::FETCH_ASSOC)["LibelleStd"];
                
                $lignesEchouees[] = [
                    'CodeDoc' => $ligne['codeDoc'],
                    'NumLig' => $ligne['numLig'],
                    'CodeElem' => $ligne['codeElem'],
                    'Libelle' => $libelle,
                    'QteRequis' => $ligne['qte'],
                    'QteDispo' => $stockDispo
                ];
                break; // Sortir de la boucle dès qu'une ligne est invalide
            }
            
            // Stocker les informations pour la mise à jour
            $stocksDisponibles[$ligne['codeElem']] = [
                'stockDispo' => $stockDispo,
                'pa' => round($dbBatigest->query(
                    "SELECT PA FROM ElementDef WHERE Code = :codeElem",
                    ['codeElem' => $ligne['codeElem']]
                )->fetch(PDO::FETCH_ASSOC)["PA"], 2)
            ];
        }
        
        // 2. Si toutes les lignes du BI sont valides, les mettre à jour
        if ($biValide) {
            foreach ($lignes as $ligne) {
                $typeMvt = 'S';
                $provenance = 'I';
                $info = $ligne['codeDoc'];
                
                $dbBatigest->query(
                    "INSERT INTO ElementMvtStock (CodeElem, TypeMvt, Provenance, Date, Quantite, PA, Info, Suivi, TypeOrigine, Origine, Destination) 
                    VALUES (:codeElem, :typeMvt, :provenance, GETDATE(), :quantite, :pa, :info, 0, '', '', '')",
                    [
                        'codeElem' => $ligne['codeElem'],
                        'typeMvt' => $typeMvt,
                        'provenance' => $provenance,
                        'quantite' => $ligne['qte'],
                        'pa' => $stocksDisponibles[$ligne['codeElem']]['pa'],
                        'info' => $info
                    ]
                );
                
                $dbBatigest->query(
                    "UPDATE ElementStock SET QttConso = QttConso + :quantite WHERE CodeElem = :codeElem",
                    [
                        'quantite' => $ligne['qte'],
                        'codeElem' => $ligne['codeElem']
                    ]
                );
                
                $nbLignesTraitees++;
            }
        }
    }
    
    if (empty($lignesEchouees)) {
        $_SESSION['success_message'] = true;
        $_SESSION['message_details'] = "$nbLignesTraitees ligne(s) mise(s) à jour avec succès.";
    } else {
        $_SESSION['success_message'] = false;
        $message = "$nbLignesTraitees ligne(s) mises(s) à jour.<br><br>Les bons d'intervention suivants n'ont pas pu être traités à cause d'un stock insuffisant :<br>";
        
        // Grouper les messages d'erreur par BI
        $erreurParBI = [];
        foreach ($lignesEchouees as $ligne) {
            if (!isset($erreurParBI[$ligne['CodeDoc']])) {
                $erreurParBI[$ligne['CodeDoc']] = [];
            }
            $erreurParBI[$ligne['CodeDoc']][] = $ligne;
        }
        
        foreach ($erreurParBI as $codeDoc => $lignes) {
            $message .= "<br><br><p><strong>$codeDoc</strong> :</p>";
            foreach ($lignes as $ligne) {
                $message .= "<strong>{$ligne['Libelle']}</strong> : " .
                           "Stock requis: {$ligne['QteRequis']}, " .
                           "Stock disponible: {$ligne['QteDispo']}";
            }
        }
        
        $_SESSION['message_details'] = $message;
    }
    
} catch (Exception $e) {
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Erreur lors du traitement : " . $e->getMessage();
}

header('Location: index.php');
exit;