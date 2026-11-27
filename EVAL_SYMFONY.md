# Bloc 2 – Symfony – Évaluation 1

## Sommaire

1. [Modélisation](#1-modélisation)
2. [Mise en route du projet NotePro](#2-mise-en-route-du-projet-notepro)
3. [Reverse engineering](#3-reverse-engineering)
4. [Sécurisation](#4-sécurisation)
5. [Nouvelles fonctionnalités](#5-nouvelles-fonctionnalités)
   - [5.1 Calcul des moyennes](#51-calcul-des-moyennes)
   - [5.2 Catégorisation des évaluations](#52-catégorisation-des-évaluations)
   - [5.3 Afficher « Mes profs »](#53-afficher--mes-profs-)
6. [Annexe : un peu d'aide](#6-annexe--un-peu-daide)

---

## 1. Modélisation

NotePro est la super nouvelle appli qui va révolutionner le monde de l'éducation et remplacer cette bonne vieille application Pronote.

NotePro sera mieux et moins cher 😊

Voici les éléments qui vont permettre de modéliser NotePro :

Dans NotePro, nous avons des utilisateurs qui sont soit des professeurs, soit des étudiants. Un utilisateur possède un id, un nom, un email. Un professeur enseigne à une ou plusieurs classes et enseigne une ou plusieurs matières. Une classe est connue avec un simple libellé, tout comme une matière.

Un étudiant est inscrit dans une classe.

Un professeur donne des évaluations à ses classes (une évaluation pour une classe bien sûr !). Lorsqu'il crée une évaluation, il lui donne un titre et un barème (/10 ou /20 ou sur n'importe quel nombre de points) et précise la date de l'évaluation.

Pour chaque évaluation, il attribue une note à chaque étudiant de la classe à qui il a donné l'évaluation et éventuellement coche l'étudiant « Absent » s'il n'était pas présent lors de l'évaluation.

Proposer un diagramme de classe pour NotePro. Rendez ce travail pour passer à la suite.

---

## 2. Mise en route du projet NotePro

Récupérez le projet : <https://github.com/zet74prof/notepro.git>

En suivant ce qui est écrit dans le README du projet :

- Créer le fichier `.env.local` pour mettre en place le chemin vers la BDD.
- Créer la BDD et mettre à jour le schéma.
- Démarrer le serveur Symfony.
- Installer les fixtures pour obtenir des données de test.

---

## 3. Reverse engineering

**Objectif :**

- Savoir expliquer le fonctionnement d'un code fourni ou comment faire du « reverse engineering ».

### 3.1 Formulaire de mot de passe réutilisé

Expliquer, dans l'encart ci-dessous, en quelques lignes, la mécanique permettant de réutiliser le même formulaire pour le changement du mot de passe d'un utilisateur connecté et la réinitialisation d'un mot de passe oublié (où l'utilisateur n'est donc pas connecté). Vous expliquerez notamment comment, dans le cas du mot de passe oublié, le champ de saisie de l'ancien mot de passe n'apparaît pas, et pourquoi il apparaît pour le changement d'un utilisateur connecté.

> **Réponse :**
>
> Le même formulaire (`ChangePasswordFormType`) est utilisé par les deux contrôleurs, mais le champ de l'ancien mot de passe est **ajouté ou retiré dynamiquement** :
>
> - Dans `ChangePasswordController` (utilisateur **connecté**), le formulaire est créé tel quel : le champ `plainPasswordCurrent` est présent, avec la contrainte `UserPassword` (`src/Form/ChangePasswordFormType.php`). Cette contrainte vérifie que la saisie correspond bien au mot de passe hashé de l'utilisateur actuellement connecté, ce qui empêche quelqu'un de changer le mot de passe si la session est restée ouverte sans surveillance.
> - Dans `ResetPasswordController::reset()` (mot de passe **oublié**, utilisateur non connecté), la ligne `$form->remove('plainPasswordCurrent');` (ligne 109) supprime ce champ **avant** le `handleRequest()` et le rendu. Un champ retiré du builder n'est ni affiché dans le template (`reset_password/reset.html.twig`), ni soumis, ni validé : c'est pourquoi l'ancien mot de passe n'apparaît pas et n'est pas demandé.
>
> Le champ `plainPassword` (nouveau mot de passe, en `RepeatedType`, `mapped => false`) est en revanche commun aux deux cas : il est lu dans le contrôleur avec `$form->get('plainPassword')->getData()`, hashé puis enregistré, que l'utilisateur soit connecté ou non. L'utilisateur n'étant pas connecté, il ne peut pas être authentifié par son ancien mot de passe : c'est le **token unique et expirable** reçu par mail (généré par le bundle `symfonycasts/reset-password`) qui joue ce rôle d'authentification.

### 3.2 Contrôle des 5 derniers mots de passe

Analyser le code de l'application NotePro et expliquer comment a été implémenté le contrôle qui empêche un utilisateur de saisir l'un de ses 5 derniers mots de passe. L'explication doit être précise et détaillée, et rédigée dans un document que vous rendrez.

(Précisions attendues : quelle(s) route(s), quel(s) controller(s), lignes de code concernées et explications de celles-ci, …)

> **Réponse :**
>
> Le contrôle repose sur une entité dédiée `PreviousPasswords` (`src/Entity/PreviousPasswords.php`) qui stocke l'historique des mots de passe : chaque mot de passe remplacé est hashé à nouveau (avec le hash salté propre à cet enregistrement) et lié à son utilisateur par une relation `ManyToOne` vers `User`. Dans l'entité `User`, la relation inverse `previousPasswords` (OneToMany) permet de retrouver tout l'historique.
>
> Le contrôle est effectué à deux endroits, avec la même logique :
>
> 1. **`ChangePasswordController`** (`/changepassword`, utilisateur connecté), lignes ~30-40 : avant d'enregistrer le nouveau mot de passe, une boucle parcourt les **5 derniers** mots de passe de l'historique (du plus récent au plus ancien, indices `$nbPreviousPasswords - 1` à `$nbPreviousPasswords - 5`) et appelle `$passwordHasher->isPasswordValid($user->getPreviousPasswords()[$i], $form->get('plainPassword')->getData())` pour chacun. `isPasswordValid` re-hash le mot de passe saisi avec le sel de l'enregistrement d'historique et le compare. Si l'un des 5 correspond, un flag `$passOk` est positionné ; dans ce cas le contrôleur refuse l'enregistrement et ajoute un flash « Vous ne pouvez pas réutiliser l'un de vos 5 derniers mots de passe », sans modifier le mot de passe.
> 2. **`ResetPasswordController::reset()`** (`/reset-password/reset/{token}`, mot de passe oublié), mêmes lignes ~118-128 : la boucle identique est exécutée après validation du token. Si le mot de passe proposé figure parmi les 5 derniers, flash d'erreur et redirection vers la page de réinitialisation ; sinon le mot de passe est hashé et enregistré, **et** un nouvel enregistrement `PreviousPasswords` contenant le mot de passe sortant est créé et persisté, ce qui alimente l'historique pour les prochains contrôles.
>
> Point important : on ne peut pas comparer directement les mots de passe en clair, puisque seuls des hash sont stockés. C'est pourquoi on utilise `isPasswordValid` sur chaque enregistrement d'historique : le hasher utilise le sel/paramètres encodés dans le hash stocké, ce qui permet de tester l'égalité sans jamais stocker le mot de passe en clair.
>
> Le même besoin est également formalisé sous forme d'une contrainte de validation réutilisable : `src/Validator/PasswordHistory.php` (l'annotation) et `src/Validator/PasswordHistoryValidator.php` (le validateur, qui parcourt tous les mots de passe précédents et construit une violation si le mot de passe saisi en réutilise un).

---

## 4. Sécurisation

### 4.1 Entropie des mots de passe

Répondez à la User Story ci-dessous :

> « En tant que RSSI, je souhaite que les mots de passe des utilisateurs aient une entropie supérieure ou égale à 60 afin de se protéger d'éventuelles attaques »

_(Note : le calcul de l'entropie est donné par la méthode `estimateStrength` de la classe `PasswordStrengthValidator` (cf. doc Symfony ou code du framework).)_

Notez bien que pour l'instant, la seule contrainte vérifiée est que le mot de passe ne soit pas vide (contrainte `NotBlank`) et que `PasswordStrength` est une contrainte au même titre que `NotBlank`.

Précisez ci-dessous comment vous avez procédé (classes ou fichiers de configuration mis à jour).

> **Réponse :**
>
> La contrainte `PasswordStrength` de Symfony a été ajoutée sur le champ « nouveau mot de passe » de deux formulaires, en plus de `NotBlank` qui empêche uniquement un mot de passe vide :
>
> - `src/Form/RegistrationFormType.php` (inscription) : contrainte `new PasswordStrength(['minScore' => PasswordStrength::STRENGTH_WEAK, ...])` sur le premier champ du `RepeatedType` du mot de passe.
> - `src/Form/ChangePasswordFormType.php` (changement / réinitialisation du mot de passe) : même contrainte, avec `minScore` à `STRENGTH_STRONG` (encore plus exigeant).
>
> Pourquoi `STRENGTH_WEAK` donne une entropie ≥ 60 : dans `PasswordStrengthValidator::estimateStrength()` (du composant `symfony/validator`), l'entropie du mot de passe est calculée (longueur × log₂(taille du pool de caractères), ligne 81), puis convertie en un score de 0 à 4 selon des paliers fixes : ≥ 60 bits → `STRENGTH_WEAK` (score 1), ≥ 80 → `STRENGTH_MEDIUM` (2), ≥ 100 → `STRENGTH_STRONG` (3), ≥ 120 → `STRENGTH_VERY_STRONG` (4). En exigeant `minScore => STRENGTH_WEAK` (le minimum possible, car le constructeur refuse `minScore < 1`), tout mot de passe dont l'entropie estimée est **inférieure à 60 bits** est rejeté avec le message « saisissez un mot de passe plus complexe ». Le besoin RSSI (entropie ≥ 60) est donc bien couvert ; la réinitialisation et le changement de mot de passe demandent même un score plus élevé.

### 4.2 Restriction de `/configprof`

Sécurisez l'accès aux routes `/configprof` du controller `ConfigProfController` pour en restreindre l'accès aux seuls administrateurs de l'application. Précisez ci-dessous comment vous avez procédé (classes ou fichiers de configuration mis à jour).

> **Réponse :**
>
> La restriction a été mise en place dans `config/packages/security.yaml` avec une règle d'**access_control** :
>
> ```yaml
> access_control:
>     - { path: ^/configprof, roles: ROLE_ADMIN }
> ```
>
> Chaque requête dont le chemin commence par `/configprof` (routes de `ConfigProfController`, préfixe `#[Route('/configprof')]`) est donc refusée (403) pour un étudiant ou un professeur, et autorisée uniquement pour un utilisateur portant `ROLE_ADMIN`. Cela fonctionne grâce à la hiérarchie de rôles déclarée juste au-dessus dans le même fichier (`ROLE_ADMIN: [ROLE_PROFESSOR, ROLE_STUDENT]`) : l'admin hérite de tous les droits, mais l'inverse n'est pas vrai. La première règle qui correspond étant appliquée, toute tentative d'accès non-admin est bloquée avant même d'atteindre le contrôleur.

---

## 5. Nouvelles fonctionnalités

### 5.1 Calcul des moyennes

Répondez aux besoins ci-dessous exprimés sous la forme de User Stories en implémentant les solutions adéquates.

**Moyenne générale de l'étudiant**

> En tant qu'étudiant, je souhaite pouvoir consulter ma moyenne générale afin d'avoir un indicateur global de ma performance dans ma scolarité.

_Détails : la moyenne générale de l'étudiant doit être affichée dans la page « Mes notes ». Elle pourra aussi être consultable dans d'autres fonctionnalités de l'application non encore développées (ex : liste de classe avec moyenne générale de chaque étudiant)._

**Moyenne de classe par évaluation**

> En tant que professeur, je souhaite pouvoir consulter la moyenne des notes à une évaluation afin d'avoir un indicateur de la performance d'une classe.

_Détails : la moyenne de classe doit s'afficher pour chaque évaluation dans la page « Mes évaluations ». Cette moyenne pourra aussi être consultable dans d'autres fonctionnalités de l'application non encore développées (ex : affichage de cette moyenne dans la page « Mes notes » de l'étudiant)._

### 5.2 Catégorisation des évaluations

_NB : utilisez l'annexe pour vous aider._

On souhaite ajouter une catégorie pour les évaluations (exemples de catégories : DS, TP, BTS Blanc …).

Implémentez la solution.

### 5.3 Afficher « Mes profs »

Connecté en tant qu'étudiant, la page « Mes profs » n'a pas été implémentée.

Implémentez cette page qui affiche la liste des professeurs de l'étudiant.

**Bonus :**

Implémentez une fonctionnalité qui, à partir de cette page « Mes profs », va permettre d'envoyer un mail au professeur.

---

## 6. Annexe : un peu d'aide

Dans les classes des formulaires Symfony (`XxxxxType`), on utilise différentes classes pour afficher les divers éléments souhaités dans un formulaire. Par exemple :

- `TextType`, pour afficher un champ de saisie de texte,
- `EmailType`, pour afficher un champ de saisie d'un email,
- `CheckboxType`, pour afficher des cases à cocher,
- etc.

Il existe une classe très pratique : `EntityType`.

Cette classe permet de faire apparaître une liste de choix, des boutons radio ou des cases à cocher, qui liste les éléments d'une des entités de notre projet, stockés en base de données.

Imaginons que nous avons des objets de la classe `Professor` stockés en base de données : en ajoutant un champ avec la classe `EntityType` qui fera référence à la classe `Professor`, Symfony va chercher automatiquement tous les objets de la classe `Professor` et afficher la liste dans le formulaire.

Ce qui va s'afficher dans la liste (ou cases à cocher) est déterminé par le paramètre `choice_label`, auquel on dit quel attribut de l'objet on souhaite utiliser pour l'affichage.

Dans l'exemple ci-dessous, on dit que c'est l'attribut `name` de l'objet de la classe `Professor` qui s'affichera.

**Exemple :**

```php
$builder
    ->add('professor', EntityType::class, [
        'class' => Professor::class,
        'placeholder' => 'Sélectionner un professeur',
        'choice_label' => 'name',
        'multiple' => false,
        'expanded' => false,
        'label' => false,
        'mapped' => false,
    ])
```

Ceci va afficher une liste de choix.

Si les paramètres `multiple` et `expanded` passent à `true`, on aura des cases à cocher.
