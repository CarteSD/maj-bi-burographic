# Module de mise à jour du stock des bons d'interventions
Ce module permet de mettre à jour le stock des consommables utiliser lors des différentes interventions réalisées par les techniciens de Burographic. 

## Fonctionnement
À chaque exécution, le module récupère toutes les lignes des bons d'interventions non-convertis en facture. Pour chaque ligne récupérée, il vérifie si un mouvement de stock relatif existe. Si tel n'est pas le cas, il l'ajoute au tableau qui sera affiché ensuite.

Lorsque l'utilisateur se rend sur la page web `index.php`, un tableau recense l'ensemble des lignes non mises à jour calculées précédemment.

### Bouton "Mettre à jour la sélection" :
*N.b. : ce bouton est actif uniquement si au moins une ligne du tableau est sélectionnée.*

2 requêtes SQL sont exécutées :
- Insertion d'un mouvement de stock correspondant à l'élément consommé dans la table `ElementMvtStock`, en insérant dans le champ `Info` le numéro de bon d'intervention.
- Modification du stock de l'élément consommé dans la table `ElementStock` en ajoutant à l'attribut `QttConso` la quantité consommée dans la ligne du bon d'intervention.