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
    // Si aucune ligne n'est sélectionnée, rediriger avec un message d'erreur
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Aucune ligne sélectionnée.";
    header('Location: index.php');
    exit;
}

// Vérifier que l'action est bien 'update'
if (!isset($_POST['action']) || $_POST['action'] !== 'update') {
    // Si l'action n'est pas reconnue, rediriger avec un message d'erreur
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Action non reconnue.";
    header('Location: index.php');
    exit;
}

// Récupérer l'action et les lignes sélectionnées
$action = $_POST['action'];
$selectedLines = $_POST['selected'] ?? [];

try {
    $dbBatigest = Db::getInstance('batigest');
    
    // Regrouper les lignes par BI
    $lignesParBI = [];
    foreach ($selectedLines as $line) {
        // On coupe la ligne en morceaux pour obtenir le codeDoc, numLig, codeElem et qte
        $parts = explode('|', $line);

        // Vérifier que la ligne contient au moins 4 parties (codeDoc, numLig, codeElem, qte) sinon, c'est une ligne invalide
        if (count($parts) < 4) continue;
        
        // On utilise le codeDoc comme clé pour regrouper les lignes
        $codeDoc = $parts[0];
        if (!isset($lignesParBI[$codeDoc])) {
            $lignesParBI[$codeDoc] = [];
        }
        
        // Ajouter la ligne au tableau des lignes pour ce codeDoc
        $lignesParBI[$codeDoc][] = [
            'codeDoc' => $parts[0],
            'numLig' => $parts[1],
            'codeElem' => $parts[2],
            'qte' => (int)$parts[3]
        ];
    }
    
    // Initialiser les compteurs et les tableaux pour les lignes traitées et échouées
    $nbLignesTraitees = 0;
    $lignesEchouees = [];
    
    // Traiter chaque BI
    foreach ($lignesParBI as $codeDoc => $lignes) {
        $biValide = true;
        $stocksDisponibles = [];
        
        // 1. Vérifier d'abord que le stock est suffisant pour chaque ligne du BI
        foreach ($lignes as $ligne) {

            // Récupérer le stock disponible pour l'élément
            $stockDispo = $dbBatigest->query(
                "SELECT (QttAppro - QttConso) AS QttStock
                FROM ElementStock
                WHERE CodeElem = :codeElem", 
                [
                    'codeElem' => $ligne['codeElem']
                ]
            )->fetch(PDO::FETCH_ASSOC)["QttStock"];
            
            // Si le stock est insuffisant, on marque le BI comme invalide et on stocke les informations de l'élément manquant pour faire le message d'erreur
            if (!$stockDispo || $stockDispo < $ligne['qte']) {
                $biValide = false;
                $libelle = $dbBatigest->query(
                    "SELECT LibelleStd
                    FROM ElementDef
                    WHERE Code = :codeElem",
                    [
                        'codeElem' => $ligne['codeElem']
                    ]
                )->fetch(PDO::FETCH_ASSOC)["LibelleStd"];
                
                // Ajouter la ligne à la liste des lignes échouées
                $lignesEchouees[] = [
                    'CodeDoc' => $ligne['codeDoc'],
                    'NumLig' => $ligne['numLig'],
                    'CodeElem' => $ligne['codeElem'],
                    'Libelle' => $libelle,
                    'QteRequis' => $ligne['qte'],
                    'QteDispo' => $stockDispo
                ];
            }
            
            // Récupérer le prix d'achat de l'élément
            $pa = $dbBatigest->query(
                    "SELECT PA
                    FROM ElementDef
                    WHERE Code = :codeElem",
                    [
                        'codeElem' => $ligne['codeElem']
                    ]
                )->fetch(PDO::FETCH_ASSOC)["PA"];
            $pa = round($pa, 2); // Arrondir le prix d'achat à 2 décimales

            // Stocker les informations pour la mise à jour
            $stocksDisponibles[$ligne['codeElem']] = [
                'stockDispo' => $stockDispo,
                'pa' => $pa
            ];
        }
        
        // 2. Si toutes les lignes du BI sont valides, les mettre à jour
        if ($biValide) {
            foreach ($lignes as $ligne) {
                $typeMvt = 'S'; // Sortie
                $provenance = 'I'; // Intervention
                $info = $ligne['codeDoc']; // Info pour le mouvement de stock
                
                // Insérer le mouvement de stock dans la base de données
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
                
                // Mettre à jour le stock de l'élément
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
    
    // Générer le message de succès ou d'erreur
    if (empty($lignesEchouees)) { // Aucune ligne n'a échoué
        $_SESSION['success_message'] = true;
        $_SESSION['message_details'] = "$nbLignesTraitees ligne(s) mise(s) à jour avec succès.";
    } else { // Il y a des lignes qui n'ont pas pu être traitées
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
        
        // Construire le message d'erreur au format HTML
        foreach ($erreurParBI as $codeDoc => $lignes) {
            $message .= "<br><br><p><strong>$codeDoc</strong> :</p>";
            foreach ($lignes as $ligne) {
                $message .= "<strong>{$ligne['Libelle']}</strong> : " .
                           "Stock requis: {$ligne['QteRequis']}, " .
                           "Stock disponible: {$ligne['QteDispo']}<br>";
            }
        }
        
        // Enregistrer le message d'erreur dans la session
        $_SESSION['message_details'] = $message;
    }
    
} catch (Exception $e) {
    // En cas d'erreur, enregistrer le message d'erreur dans la session
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Erreur lors du traitement : " . $e->getMessage();
}

// Rediriger vers la page d'accueil avec le message de succès ou d'erreur qui est enregistré dans la session
header('Location: index.php');
exit;