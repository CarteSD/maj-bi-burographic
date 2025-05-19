# Module de mise à jour du stock des bons d'interventions
Ce module permet de mettre à jour le stock des consommables utiliser lors des différentes interventions réalisées par les techniciens de Burographic. 

## Fonctionnement
Le module utilise une seconde base de données, crées au préalable, dans laquelle il enregistre toutes les modifications qu'il a effectué. À chaque exécution, il récupère toutes les lignes des bons d'interventions non-convertis en facture ainsi que les l'ensemble des lignes mises à jour. Il effectue un tri et retourne un tableau contenant toutes les lignes qui n'ont pas encore été mises à jour.

Lorsque l'utilisateur se rend sur la page web `index.php`, un tableau recense l'ensemble des lignes non mises à jour calculées précédemment. Un bouton "Tout mettre à jour" lui permet de créer la mise à jour afin de corriger le stock des éléments consommés. 

3 requêtes SQL sont exécutées :
- Insertion d'un mouvement de stock dans la base de données de Batigest Connect, afin de déclarer et justifier la sortie de stock.
- Mise à jour de la table `ElementStock` dans cette même base de données, afin de corriger la quantité consommée en y ajoutant ce que le technicien a consommé sur place.
- Insertion dans la table `HistoMaj`, dans la base de données secondaire, permettant de stocker la prise en compte et la mise à jour de cette ligne pour qu'elle ne ressorte plus dans les prochaines exécutions.