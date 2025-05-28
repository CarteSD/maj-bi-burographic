<?php
/**
 * @file     ignore.php
 * @author   Estéban DESESSARD
 * @brief    Fichier d'ignorance des interventions
 * @details
 * @date     28/05/2025
 * @version  0.0
 */

require_once 'Db.php';

// Vérifier si la demande vient du formulaire
if (!isset($_POST['ignore']) || !is_array($_POST['ignore']) || empty($_POST['ignore'])) {
    session_start();
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Aucune ligne sélectionnée à ignorer.";
    header('Location: index.php');
    exit;
}

session_start();

try {
    $dbInterventions = Db::getInstance('interventions');

    // Récupérer les lignes à ignorer
    $lignesAIgnorer = $_POST['ignore'] ?? [];

    $nbLignesIgnorees = 0;
    foreach ($lignesAIgnorer as $line) {
        $args = explode('|', $line);
        $codeDoc = $args[0] ?? '';
        $numLig = $args[1] ?? '';
        $codeElem = $args[2] ?? '';
        $qte = $args[3] ?? 0;

        // Insérer dans la table HistoMaj
        $dbInterventions->query("INSERT INTO HistoMaj VALUES (:codeDoc, :numLig, :codeElem, :qte, 1, GETDATE())", [
            'codeDoc' => $codeDoc,
            'numLig' => $numLig,
            'codeElem' => $codeElem,
            'qte' => (int)$qte
        ]);

        $nbLignesIgnorees++;
    }
} catch (PDOException $e) {
    $_SESSION['success_message'] = false;
    $_SESSION['message_details'] = "Erreur lors de l'ignorance des lignes : " . $e->getMessage();
    header('Location: index.php');
    exit;
}

$_SESSION['success_message'] = true;
$_SESSION['message_details'] = "$nbLignesIgnorees ligne(s) ignorée(s) avec succès.";
header('Location: index.php');