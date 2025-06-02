# Module de mise à jour du stock des bons d'interventions
Ce module permet de mettre à jour le stock des consommables utiliser lors des différentes interventions réalisées par les techniciens de Burographic. 

## Fonctionnement
Le module utilise une seconde base de données, créée au préalable, dans laquelle il enregistre toutes les modifications qu'il a effectué. À chaque exécution, il récupère toutes les lignes des bons d'interventions non-convertis en facture ainsi que les l'ensemble des lignes mises à jour. Il effectue un tri et retourne un tableau contenant toutes les lignes qui n'ont pas encore été mises à jour.

Lorsque l'utilisateur se rend sur la page web `index.php`, un tableau recense l'ensemble des lignes non mises à jour calculées précédemment.

### Bouton "Tout mettre à jour" :
3 requêtes SQL sont exécutées :
- Insertion d'un mouvement de stock dans la base de données de Batigest Connect, afin de déclarer et justifier la sortie de stock.
- Mise à jour de la table `ElementStock` dans cette même base de données, afin de corriger la quantité consommée en y ajoutant ce que le technicien a consommé sur place.
- Insertion dans la table `HistoMaj`, dans la base de données secondaire, permettant de stocker la prise en compte et la mise à jour de cette ligne pour qu'elle ne ressorte plus dans les prochaines exécutions.

### Bouton "Ignorer la sélection" :
*N.b. : ce bouton est actif uniquement si au moins une ligne du tableau est sélectionnée.*

1 requête SQL est exécutée :
- Insertion dans la table `HistoMaj`, dans la base de données secondaire, en valorisant l'attribut `Ignored` à 1 pour indiquer sur cette ligne a été notée comme ignorée, et qui n'a donc pas mis à jour le stock?

### Bouton "Consulter le journal" :
Permet de consulter l'ensemble des lignes déjà traitées en indiquant pour chacune si il s'agit d'un élément ignoré, ou bien une mise à jour du stock théorique sur la base de données de Batigest Connect.

## Installation et configuration
> [!WARNING]
> Cette application nécessite de posséder Batigest Connect accompangé de sa base de données.

Après avoir cloné le dépôt, exécutez le `script.sql` sur l'instance de Batigest Connect, afin d'y ajouter la base de données secondaire.

Modifiez le fichier `config.php` en y insérant vos constantes de connexion pour les deux bases de données.