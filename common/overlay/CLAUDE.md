# {{PROJECT_TITLE}}

Projet Symfony {{SYMFONY_VERSION}} (PHP {{PHP_VERSION}}), mode **{{MODE}}**, généré depuis le
skeleton maison le {{DATE}}.

## Règle n°1 — aucune route sans action de voter

**Chaque route porte une décision d'accès explicite, et cette décision vit dans
un voter.** Une route sans décision est un bug, y compris quand elle est
publique : le silence n'autorise pas, il oublie de refuser.

- **Action avec sujet** — première instruction de la méthode :
  `$this->denyAccessUnlessGranted(UserVoter::EDIT, $user);`. C'est le seul
  endroit où le sujet de la décision existe.
- **Action sans sujet** — l'attribut sur l'action : `#[IsGranted(UserVoter::LIST)]`.
  La décision est prise avant d'entrer dans la méthode.
- **Route volontairement publique** — elle le déclare comme les autres :
  `#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]`.
- **API Platform** — la décision est portée par l'opération (`security:`, ou
  `securityPostDenormalize:` quand elle dépend du corps de la requête), jamais
  par un contrôleur.
- **Les rôles sont la matière de la décision, pas son expression.** `ROLE_ADMIN`
  se lit dans le voter (`$this->hasRole(User::ROLE_ADMIN)`) et n'apparaît ni dans
  un `denyAccessUnlessGranted`, ni dans un `is_granted` de gabarit.
  `access_control` et un `#[IsGranted('ROLE_…')]` de classe restent la ceinture ;
  le voter est la bretelle, et c'est lui qui connaît la règle fine.
- **Un gabarit affiche une action sous l'attribut qui la gouverne**
  (`{% if is_granted('USER_DELETE', user) %}`) : un bouton visible mais refusé
  est un bug d'interface, une règle recopiée dans le gabarit en est un autre.
- Seule exception, documentée dans le code : les routes interceptées par le
  pare-feu (déconnexion, échange d'identifiants), dont l'action n'est jamais
  exécutée — la règle d'accès y est le pare-feu lui-même.

Les voters héritent d'`App\Security\Voter\AbstractVoter` : un attribut par
action (`USER_LIST`, `USER_VIEW`, `USER_EDIT`…) déclaré par `attributes()`, la
décision dans `decide()`, qui ne reçoit que des comptes authentifiés. Un nouveau
sujet protégé = un nouveau voter, pas une condition de plus dans un contrôleur.

La règle n'est pas qu'un usage : `tests/Security/RouteAccessDecisionTest.php`
parcourt le routeur et échoue dès qu'une action de `App\` n'a ni `#[IsGranted]`
sur la méthode ni `denyAccessUnlessGranted()` dans son corps. Une route ajoutée
sans décision fait donc échouer `make qa`. Les deux seules portes de sortie sont
la liste `FIREWALL_ROUTES` de ce test (routes interceptées par le pare-feu) et
les actions héritées d'un bundle, qui déclarent leur décision autrement
(EasyAdmin : `setPermission()`) — les élargir se discute, ça ne se bricole pas.

## Commandes

Tout passe par le `Makefile` (`make` seul liste les cibles) :

| Commande | Effet |
| --- | --- |
| `make install` | dépendances composer |
| `make start` / `make stop` | serveur local https |
| `make cc` | vide le cache |
| `make db-diff` / `make db-migrate` | migrations Doctrine |
| `make test` | PHPUnit |
| `make cs-fix` / `make stan` | php-cs-fixer / PHPStan (niveau 8) |
| `make rector` / `make rector-fix` | Rector : vérification / application (`rector.php`) |
| `make qa` | style + Rector + analyse + tests |
| `make docs` / `make docs-check` | carte des workflows (DevTools) : mise à jour / vérification |

`composer` n'est pas installé globalement : utiliser `symfony composer …` et
`symfony console …`.

## Carte des workflows — DevTools

Le projet est documenté par [DevTools](https://github.com/jul6art/devtools) depuis
son premier commit : chaque route, commande, message et composant a sa page dans
`docs/workflows/`, et **`docs/workflows/workflows.md` en est le sommaire**.

- **Avant de modifier un workflow, lire sa page** : ce qu'il fait, ce qu'il
  décide, ce qu'il faut surveiller. `vendor/bin/devtools workflows:impact <fichier>`
  dit quels workflows un fichier touche — le hook `PreToolUse` le fait déjà avant
  chaque modification.
- **Après un changement**, `make docs` met les faits à jour ; la prose des pages
  s'écrit avec le skill `/devtools-inspect`, dont les brouillons sont validés par
  `vendor/bin/devtools workflows:apply`. Une question sur le comportement :
  `/devtools-ask`.
- Le hook `Stop` refuse de clore une tâche tant que la gate échoue (carte
  périmée, scénario rejoué en échec) : on corrige, on ne contourne pas.
- `.devtools/` et `docs/workflows/` se committent ; les zones de travail
  (`.devtools/pending/`, `reports/`, `runs/`, `gate/`, `stats/`) sont ignorées.
- Rien n'appelle l'API Claude hors session : `devtools ai:run` et `devtools ask`
  restent coupés tant que `<ai enabled="true"/>` n'est pas posé dans
  `.devtools/config.xml`.

## Bundles maison — à utiliser, pas à réécrire

> Table à jour jusqu'à `core-bundle` **v2.9.1** (2026-08-20). Le squelette a suivi les lots 0
> à 7 du chantier d'extraction ; les lots suivants créent d'autres bundles (`audit`, `push`,
> `api`, `ui`, `acl`) qu'il n'installe pas. À compléter le jour où `core-bundle` ou
> `auth-bundle` gagne une brique.

Deux bundles portent les briques communes. **Avant d'écrire une classe, vérifier
si le core-bundle ne la fournit pas déjà** : tout ce qu'il définit doit être
utilisé plutôt que réimplémenté.

### `jul6art/core-bundle` (`Jul6Art\CoreBundle`)

| Brique | Usage attendu |
| --- | --- |
| `Entity\Traits\IdTrait` | l'identifiant de **toute** entité (`id` + `getId()`) : ne jamais redéclarer un `$id` |
| `Entity\Traits\TimestampableTrait` | `created_at` + `updated_at`, posés par les callbacks de cycle de vie — **exige `#[ORM\HasLifecycleCallbacks]` sur l'entité** |
| `Entity\Traits\CreatedAtTrait` | `created_at` seul, quand un journal d'audit couvre déjà les modifications — **exclusif** de `TimestampableTrait` |
| `Entity\Traits\SoftDeletableTrait` | `deleted_at` + `softDelete()` / `restore()` / `isDeleted()` ; ne masque rien par lui-même |
| `Entity\Traits\AuditableTrait` | `created_by` / `updated_by` : **rien ne les remplit tout seul**, c'est le service qui appelle les setters |
| `Util\Strings::markDeleted()` / `restoreDeleted()` | libère puis restitue une valeur de colonne UNIQUE autour d'une suppression douce |
| `Repository\AbstractRepository<T>` | classe mère de **tout** dépôt : apporte `save()`, `delete()`, `flush()`, `clear()` |
| `Repository\Interfaces\RepositoryInterface<T>` | contrat à étendre pour l'interface d'un dépôt |
| `Manager\AbstractManager<T>` | classe mère de **tout** manager : `getAll()`, `getById()`, `save()`, `delete()`, `flush()`, `clear()` |
| `Manager\Interfaces\ManagerInterface<T>` | contrat à étendre pour l'interface d'un manager |
| `Factory\Interfaces\FactoryInterface` | toute fabrique d'entité (`static create()`) |
| `Event\AbstractEvent` + `EventInterface` | tout événement métier (porte une `ArrayCollection` de données) |
| `EventListener\AbstractEventListener` | tout écouteur d'événement (fournit le `TokenStorage`, donc l'auteur de l'action) |
| `EntityListener\AbstractEntityListener` | tout écouteur d'entité Doctrine (flash bag + token storage + traducteur) |
| `Service\Traits\*AwareTrait` | injection par setter `#[Required]` : `EntityManagerAware`, `EventDispatcherAware`, `FakerAware`, `FlashBagAware`, `TokenStorageAware`, `TranslatorAware` |
| `Service\Traits\TokenStorageAwareTrait` | qui agit : `getCurrentUserOrNull()`, `getCurrentUserIdOrNull()`, et `getOriginalUserIdOrNull()` — l'administrateur derrière une usurpation d'identité |
| `Security\Voter\AbstractVoter` | classe mère de **tout** voter : déclarer `attributes()`, `subjects()`, `decide()` et rien d'autre |
| `Controller\AbstractController` | classe mère de **tout** contrôleur web : flashes déjà traduits (`addSuccessFlash()`), redirections (`redirectBack()`, `redirectAfterSave()`), `addFormError()`, `trans()` |
| `Service\FlashTranslator` | résout le domaine d'un flash — par préfixe de clé (`core.flash.domain_map`) ou par contrôleur (`translationDomain()`) |
| `Controller\BulkActionRunner` | action de masse d'un tableau : jeton CSRF, `ids[]`, chargement en **une** requête, voter ligne par ligne, une seule transaction |
| `Attribute\Purgeable` + `Command\PurgeCommand` | rétention : annoter l'entité, la commande `core:purge` fait le reste |
| `Event\EntityPurgedEvent` | ce que dispatche la purge, une fois par ligne supprimée — s'y abonner pour tracer |
| `Doctrine\SoftDeleteFilter` + `Service\CascadeSoftDeleteHelper` | suppression douce : filtre SQL global + patterns de cascade vers les enfants |
| `Doctrine\DQL\JsonTextFunction` | `JSON_TEXT(colonne)` pour chercher au `LIKE` dans une colonne JSON, quelle que soit la base |
| `Security\Encryptor` + `Doctrine\Type\EncryptedStringType` | chiffrement au repos d'une colonne : `#[ORM\Column(type: 'encrypted_string')]` |
| `EventListener\SecurityHeaderListener` | en-têtes de sécurité HTTP + CSP, pilotés par `core.security_headers` — ne jamais reposer ces en-têtes à la main |
| `Security\CspNonce` + `csp_nonce()` | le nonce de la page pour un `<script>` inline (`nonce="{{ csp_nonce() }}"`), ajouté à `script-src` par le listener — jamais `'unsafe-inline'`, jamais de `onclick="…"` (≥ 3.6) |
| `Security\MathCaptchaService` | captcha des formulaires publics : `generate()` au rendu, `validate()` à la soumission |
| `Service\NumberFormatter` + `Twig\NumberExtension` | **tout** nombre affiché à un humain : filtres `format_number`, `format_money`, `format_percent` — plus jamais de `\|number_format(...)` écrit à la main |
| `Twig\PdfAssetExtension` | images d'un PDF : `pdf_image_path()` ou `pdf_image_data_uri()`, jamais `asset()` — dompdf ne résout pas les URL |
| `Form\Transformer\StripWhitespaceTransformer` | champ masqué contre colonne de longueur fixe (SIRET, IBAN) : retire les séparateurs à la soumission |
| `Form\Extension\NumberTypeGroupingExtension` | groupement des milliers sur tous les `NumberType` (opt-in `core.form.number_grouping`) |
| `Util\Strings` | normalisation de casse UTF-8 dans les setters (`upper()`, `lower()`, `lowerEmail()`) et **la** définition du marqueur `_DELETED_` |
| `Event\PersistenceAbortedException` | levée par `AbstractEntityListener` quand un événement `BEFORE_*` est avorté |

Règles qui en découlent :

- **Un manager par entité**, nommé `FooManager` avec une propriété
  `$fooRepository` : `AbstractManager` la résout par réflexion, le nom n'est pas
  décoratif. Fournir aussi `FooManagerAwareTrait` si d'autres services en ont besoin.
- Les contrôleurs et les commandes passent par les **managers**, pas par
  `EntityManagerInterface` : plus de `persist()`/`flush()` dispersés.
- Les entités se créent par une **fabrique** (`FactoryInterface`), jamais avec
  `new` dans un contrôleur.
- Un changement d'état qui intéresse le reste de l'application se **dispatche**
  (`AbstractEvent` + un écouteur héritant d'`AbstractEventListener`).
- **Tout voter étend `Security\Voter\AbstractVoter`** : trois méthodes à écrire
  (`attributes()`, `subjects()`, `decide()`), jamais `supports()` ni
  `voteOnAttribute()`. Les rôles se lisent avec `hasRole()`, qui passe par
  `Security::isGranted()` et **respecte donc `role_hierarchy`** — pas
  `$token->getRoleNames()`, qui ignore la hiérarchie. Un attribut ouvert à un
  visiteur sans compte doit le dire par `decideForAnonymous()`.
- **Toute commande destinée à un ordonnanceur prend un verrou Symfony** avant de
  travailler (`LockFactory::createLock()`, TTL supérieur à la durée du pire cas et
  inférieur à l'intervalle du cron, `release()` dans un `finally`). Une seconde
  instance sort en `Command::SUCCESS`, pas en `FAILURE` : empêcher la concurrence
  n'est pas une erreur. `symfony/lock` est installé pour ça. Les commandes
  interactives one-shot (création de compte, imports manuels) en sont dispensées.
- **Tout nombre affiché passe par les filtres du bundle** (`format_number`, `format_money`,
  `format_percent`) : un `|number_format(...)` écrit à la main dans un template est une
  erreur. ⚠️ **Sauf** quand la valeur n'est pas un affichage : un attribut HTML
  (`<progress value="…">`, `<input value="…">`), une clé JSON consommée par du JS ou un
  calcul intermédiaire doivent rester des nombres bruts — un séparateur décimal virgule les
  casse.
- **Une image destinée à un PDF passe par `pdf_image_path()` ou `pdf_image_data_uri()`**,
  jamais par `asset()` : dompdf ne fetche pas les URL distantes en production et n'a pas de
  base pour résoudre une URL relative — l'image ne se charge simplement pas, sans erreur.
- **Un formulaire public exposé aux robots porte un captcha** (inscription, mot de passe
  oublié) : injecter `MathCaptchaService`, appeler `generate()` à **chaque** rendu — y
  compris après un échec, puisque `validate()` consomme la réponse attendue dans tous les
  cas. Réservé aux formulaires : pour un client JSON, utiliser reCAPTCHA ou hCaptcha.
- **Les tests d'architecture gardent la configuration de production** (`tests/Architecture/`) : hôtes
  de confiance, en-têtes et garde-fou des secrets (core-bundle), pare-feu (auth-bundle), sécurité
  de chaque opération d'API (api-bundle). Les classes y sont vides : les règles vivent dans les
  bundles. Un rouge dit quel réglage manque ; ne pas l'exempter sans écrire pourquoi.
- **Un poste qui veut un cache `prod` obtient ses secrets par `make secrets`** : il écrit dans
  `.env.local` ceux qui manquent (sans les afficher ni remplacer une valeur posée) ; la phrase JWT
  du poste est reprise telle quelle, la paire de clés va avec elle.
- **Les en-têtes de sécurité sont configurés, pas codés** : `core.security_headers.enabled`
  suffit. Le listener ne remplit que les en-têtes **absents**, donc un contrôleur qui a
  besoin de `X-Frame-Options: SAMEORIGIN` (aperçu dans une iframe) le pose lui-même et
  garde la main. La CSP est **bloquante** (`csp_enforce: true`), sauf en développement où elle
  ne fait que rapporter : un hôte tiers que l'application appelle s'ajoute à `csp_policy`, sinon
  le navigateur le bloque en production sans rien afficher d'autre qu'une ligne de console.
- **Une piste d'audit doit enregistrer les deux identités.** Pendant un `_switch_user`,
  `getCurrentUserIdOrNull()` rend le compte **usurpé** — ce que veut la plupart du code, et
  précisément ce qui rend une trace mensongère si on s'arrête là : elle dit que l'utilisateur a
  fait ce qu'un administrateur a fait en son nom. `getOriginalUserIdOrNull()` rend
  l'administrateur, et `null` sur une connexion réelle. Consigner les deux.
- **Une entité horodatée porte `#[ORM\HasLifecycleCallbacks]`.** Sans cette attribute, Doctrine
  ignore les `#[ORM\PrePersist]` / `#[ORM\PreUpdate]` des traits **en silence** : les colonnes
  existent et ne sont jamais remplies, et la panne se manifeste plus tard par une violation
  `NOT NULL` à l'insertion — un message qui ne mentionne ni le trait ni l'attribute manquante.
  `SoftDeletableTrait` et `AuditableTrait` n'ont aucun callback, donc rien à déclarer.
- **`TimestampableTrait` et `CreatedAtTrait` sont exclusifs** : les deux déclarent `$createdAt` et
  `onPrePersist()`, donc les utiliser ensemble est une erreur fatale, pas une fusion. Choisir
  `CreatedAtTrait` quand un journal d'audit enregistre déjà qui a modifié quoi et quand — des
  colonnes `updated_at` / `updated_by` que personne ne remplit restent NULL, et un `updated_at`
  NULL se lit « jamais modifié ».
- **Les horodatages n'ont pas de setter, volontairement**, et `created_at` est mappé
  `updatable: false`. Une fixture qui a besoin d'une ligne antidatée passe par la réflexion, et
  c'est délibéré : rien ne réécrit l'histoire par accident.
- **Une suppression douce ne masque rien par elle-même.** Le trait stocke une date ; c'est
  `Doctrine\SoftDeleteFilter` qui exclut la ligne de toutes les requêtes et
  `Service\CascadeSoftDeleteHelper` qui propage aux enfants. Sans l'un des deux, la ligne
  supprimée continue de s'afficher.
- **Une colonne UNIQUE se libère avec `Util\Strings::markDeleted()`** avant la suppression douce,
  et se restitue avec `restoreDeleted()`. Sinon recréer une ligne avec la même valeur qu'une ligne
  supprimée échoue à l'insertion. Ces deux fonctions ne sont **pas** sur le trait : PHP 8.5
  déprécie l'appel d'une méthode statique sur un trait, et ce sont des opérations sur une chaîne,
  pas du comportement d'entité.
- **Une entité dont les lignes s'accumulent porte `#[Purgeable]`** (logs, jetons
  expirés, files de traitement) : `core:purge` applique la rétention, et
  `core:purge --dry-run` la vérifie avant. Pour tracer les suppressions,
  s'abonner à `EntityPurgedEvent::NAME` — la commande n'écrit aucun journal
  elle-même.
- **Tout contrôleur web étend `Controller\AbstractController`** — un contrôleur d'API
  n'en a que faire et reste sur celui de Symfony. Ce qu'il apporte : plus aucun
  `TranslatorInterface` injecté pour dire « enregistré », et un flash **déjà traduit**
  (`addSuccessFlash('flash.account.created')`).
  - Le domaine se décide de deux façons exclusives. Par **préfixe de clé** quand les clés le
    portent (`core.flash.domain_map: {'user.': 'user'}`) ; par **contrôleur** quand les
    catalogues sont nommés par écran, en surchargeant `translationDomain()`. Le second
    gagne sur le premier. Ce squelette range tous ses flashes sous `flash.` dans
    `messages`, donc il ne configure ni ne surcharge rien.
  - `trans()` traduit dans ce même domaine sans rien flasher : sujet d'un courriel,
    message d'un `createNotFoundException()`. C'était la dernière raison d'injecter le
    traducteur.
  - Un message à substitutions passe par le **quatrième** argument des redirections :
    `redirectWithSuccess('route', 'flash.account.updated', [], ['%email%' => …])` — le
    troisième porte les paramètres de **route**, confondre les deux est le piège.
  - `redirectAfterDelete()` ignore volontairement le `Referer` : après une suppression, la
    page de détail qu'il désigne renverrait 404.
- **Une action de masse passe par `Controller\BulkActionRunner`**, jamais par une boucle
  écrite à la main : il valide le jeton CSRF, lit `ids[]`, charge les lignes en une requête,
  interroge le voter **ligne par ligne** et enveloppe le tout dans une seule transaction.
  Refuser une ligne pour une raison métier se fait en levant `\DomainException` depuis le
  callable — les autres lignes du lot ne sont pas perdues ; toute autre exception annule le
  lot entier. Le formulaire doit donc poster `ids[]` et `_token`.
- `config/packages/core.yaml` porte la configuration du bundle (`email_debug`
  pour recevoir les logs critiques par e-mail en production, `purge.batch_size`,
  `encryption_key` pour activer le chiffrement au repos).

### `jul6art/auth-bundle` (`Jul6Art\AuthBundle`)

- L'utilisateur **est** `Jul6Art\AuthBundle\Entity\User` : ne jamais créer
  `App\Entity\User`, ni l'étendre (c'est une entité concrète, pas une
  `MappedSuperclass`). Son mapping Doctrine est déclaré dans
  `config/packages/auth.yaml`.
- Injecter `UserManagerInterface` et `UserRepositoryInterface` (le bundle en
  fournit les alias), créer les comptes avec `UserFactory::create()`.
- `UserRepository` implémente `PasswordUpgraderInterface` : le réencodage des
  mots de passe est automatique, ne pas le refaire.
- L'entité vendor ne porte volontairement aucune règle applicative : les
  contraintes de validation vivent dans `config/validator/user.yaml` et les
  groupes de sérialisation dans `config/serializer/user.yaml`. C'est là qu'on
  ajoute une règle, jamais dans `vendor/`.
- **La même raison s'applique à une propriété déclarée dans un trait.** Une
  attribute `#[Groups]` est bien lue sur une propriété de trait, mais elle ne
  peut pas **varier d'une entité à l'autre** : le trait finit par porter l'union
  des groupes de tous ses consommateurs, et devient impossible à sortir dans un
  bundle. Les groupes d'une propriété partagée (`createdAt`, `deletedAt`, `id`…)
  se déclarent donc dans `config/serializer/`, par classe. Une propriété
  déclarée dans l'entité, elle, garde son attribute sur place.
- `config/serializer/` est chargé **par convention** dès que le répertoire
  existe : ne pas ajouter `framework.serializer.mapping.paths`. Corollaire,
  `debug:config framework serializer` affiche `paths: {}` même quand le mapping
  est chargé — c'est `debug:serializer <FQCN>` qui répond.
- Rôles : `User::ROLE_USER` (implicite, ajouté par `getRoles()`) et
  `User::ROLE_ADMIN` — utiliser les constantes, pas des chaînes.

## Conventions

- `declare(strict_types=1);` dans tous les fichiers PHP.
- **Rector est dans la porte** (`make rector`). Une règle qui gêne s'écarte dans `rector.php` avec sa
  raison, comme les huit qui y sont déjà : elles changeaient le comportement sans qu'aucun outil le voie.
- Classes `final` par défaut ; injection par constructeur, jamais de conteneur
  de services en dépendance.
- Contrôleurs fins : ils valident l'entrée, déclarent leur décision d'accès
  (règle n°1) et délèguent à un service de `src/`.
- Pas de logique métier dans les entités Doctrine autre que des invariants.
- Nommage et messages de commit en anglais, commentaires en français.
- Toute modification de schéma passe par une migration versionnée (jamais
  `doctrine:schema:update`).
- **`src/` à plat, puis par modules.** Un projet naît rangé par type (`Controller/`, `Entity/`,
  `Form/`, `Repository/`…). Il passe à `src/Modules/<Domaine>/` (chaque module porte ses contrôleurs,
  entités, formulaires, services ; modèles : superp, devinthecode) **au-delà de 300 classes ou de quatre
  domaines métier**, et `deptrac` garde alors les dépendances entre modules. ⚠️ Ne pas mélanger les deux
  rangements à la racine de `src/` : un dossier métier à côté de `Controller/` est le signe que le seuil
  est franchi — ce passage s'écrit dans un ADR, il ne se fait pas au fil de l'eau.

## Definition of done

`make qa` passe (php-cs-fixer, Rector, PHPStan niveau 8, PHPUnit) avant de considérer
une tâche terminée — et toute route ajoutée ou modifiée porte son action de
voter (règle n°1), sans quoi la tâche n'est pas terminée non plus.
