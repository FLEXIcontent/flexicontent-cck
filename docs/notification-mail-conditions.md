# Spécification — Conditions dans les templates d'email de notification FlexiContent

- **Version** : 1.0 (brouillon)
- **Date** : 2026-11-09
- **Composant** : `com_flexicontent`
- **Fichiers concernés** : `admin/helpers/notificationmail.php`, `admin/models/parentclassitem.php`, `admin/config.xml`, fichiers de langue
- **Statut** : phase 1 implémentée (parseur, contexte brut, sous-classe `FlexicontentMailTemplate`), à valider en intégration

---

## 1. Objectif

Permettre aux administrateurs d'insérer, dans les templates d'email des notifications FlexiContent, des blocs conditionnels évalués à l'envoi.

Les conditions doivent permettre de :

- varier le contenu selon la **catégorie** de l'article ;
- varier le contenu selon la **valeur d'un champ personnalisé** FlexiContent ;
- masquer un libellé ou un paragraphe lorsqu'un champ est vide ;
- afficher un contenu différent selon l'état, la langue ou le type de contenu ;
- conserver **un seul template Joomla par scénario**, sans créer un template par catégorie.

L'objectif est d'éviter l'explosion du nombre de templates dans `#__mail_templates` et de garder `com_mails` comme point d'édition unique.

---

## 2. Périmètre

### 2.1 Inclus

- Blocs conditionnels dans le **sujet**, le **corps texte** et le **corps HTML** des templates de notification FlexiContent.
- Conditions sur la catégorie principale, les catégories assignées, l'état, la langue, le type et les champs personnalisés.
- Opérateurs de présence, d'égalité, d'appartenance, de containment et de comparaison numérique.
- Blocs imbriqués, `elseif`, `else`.
- Journalisation des erreurs en mode debug.
- Documentation utilisateur dans l'écran de configuration et dans `com_mails`.

### 2.2 Exclu

- Boucles ou itérations sur les valeurs d'un champ.
- Arithmétique ou appels de fonctions.
- Conditions sur les destinataires (elles ne changent pas l'audience).
- Conditions dans les noms de pièces jointes.
- Conditions dans le corps legacy généré en PHP lorsque le système de templates Joomla est indisponible.
- Conditions dans les templates d'autres extensions.
- Édition visuelle des conditions (pas de constructeur de règles).
- Regex, `starts with`, `ends with` (extensions futures possibles).

---

## 3. Terminologie

| Terme | Définition |
|---|---|
| **Template** | Ligne de `#__mail_templates` identifiée par `template_id` et `language`. |
| **Tag** | Remplacement simple de la forme `{TAG}`, géré par `MailTemplate::replaceTags()`. |
| **Condition** | Expression évaluée à l'envoi, de la forme `{if expression}...{/if}`. |
| **Bloc conditionnel** | Ensemble `{if}`, `{elseif}`, `{else}`, `{/if}`. |
| **Contexte** | Ensemble de valeurs brutes utilisées pour évaluer les conditions. |
| **Contexte affichage** | Ensemble de valeurs utilisées pour remplacer les tags `{TAG}`. |
| **Champ** | Champ personnalisé FlexiContent (objet avec `name`, `value`, `iscore`, etc.). |

Il faut distinguer :

- **contexte affichage** : `title`, `state` (libellé traduit), `created` (date formatée), etc. ;
- **contexte condition** : `state_id` (entier), `catid` (entier), valeurs brutes des champs, etc.

Les conditions utilisent toujours le **contexte condition**, jamais les valeurs d'affichage.

---

## 4. Architecture actuelle

Le flux actuel est le suivant :

1. `ParentClassItem::sendNotificationEmails()` construit les données du template.
2. `FlexicontentHelperNotificationMail::send()` sélectionne le template et instancie `MailTemplate`.
3. `MailTemplate::send()` charge la ligne du template, traduit les éventuelles clés de langue, remplace les tags, puis envoie.

Les notifications sont évaluées à chaque sauvegarde. Le mode debug n'affiche que des informations, il ne conditionne plus l'envoi.

---

## 5. Syntaxe

### 5.1 Principe général

Les conditions utilisent la même syntaxe `{...}` que les tags, mais avec des espaces et des opérateurs. Elles ne peuvent pas entrer en collision avec un tag Joomla classique, car un tag est un identifiant simple sans espace ni opérateur.

Exemple minimal :

```text
{if catid=11}
Contenu réservé à la catégorie 11.
{/if}
```

Un template sans aucun `{if` conserve exactement son comportement actuel.

### 5.2 Blocs

Syntaxe canonique :

```text
{if expression}
...
{elseif expression}
...
{else}
...
{/if}
```

- `{if ...}` ouvre un bloc.
- `{elseif ...}` est optionnel et peut être répété.
- `{else}` est optionnel et ne peut apparaître qu'une fois par bloc.
- `{/if}` ferme le bloc.
- Les blocs peuvent être imbriqués.
- Les mots-clés `if`, `elseif`, `else`, `and`, `or`, `not`, `in`, `contains`, `empty`, `true`, `false`, `null` sont **insensibles à la casse**.
- Les noms de variables sont **insensibles à la casse**.

### 5.3 Opérateurs

#### Opérateurs de présence

| Syntaxe | Signification |
|---|---|
| `{if variable}` | La variable est « truthy ». |
| `{if !variable}` | La variable est « falsy ». |
| `{if variable empty}` | La variable est strictement vide. |
| `{if !variable empty}` | La variable n'est pas strictement vide. |

#### Opérateurs de comparaison

| Syntaxe | Signification |
|---|---|
| `{if variable=value}` | Au moins une valeur égale `value`. |
| `{if variable!=value}` | Aucune valeur n'égale `value`. |
| `{if variable in v1,v2,v3}` | Au moins une valeur est dans la liste. |
| `{if variable not in v1,v2,v3}` | Aucune valeur n'est dans la liste. |
| `{if variable contains value}` | Au moins une valeur contient la sous-chaîne. |
| `{if variable not contains value}` | Aucune valeur ne contient la sous-chaîne. |
| `{if variable>value}` | Au moins une valeur numérique est supérieure. |
| `{if variable>=value}` | Au moins une valeur numérique est supérieure ou égale. |
| `{if variable<value}` | Au moins une valeur numérique est inférieure. |
| `{if variable<=value}` | Au moins une valeur numérique est inférieure ou égale. |

`==` est accepté comme alias de `=`. `&&` et `||` sont acceptés comme alias de `and` et `or`.

#### Opérateurs logiques

| Syntaxe | Signification |
|---|---|
| `and` / `&&` | ET logique. |
| `or` / `\|\|` | OU logique. |
| `!` / `not` (préfixe) | Négation. |

Priorité, de la plus forte à la plus faible :

1. `!` / `not` (négation unaire) ;
2. `and` / `&&` ;
3. `or` / `||`.

Les parenthèses ne sont pas supportées en v1. Une expression trop complexe doit être découpée en blocs imbriqués.

Exemples :

```text
{if catid=11 and field_floors=3}
...
{/if}

{if catid=11 or incat in 18,19}
...
{/if}

{if !field_description}
Aucune description fournie.
{/if}
```

### 5.4 Valeurs littérales

| Type | Exemple |
|---|---|
| Nombre | `11`, `3.14`, `-5` |
| Chaîne simple | `Lyon` |
| Chaîne entre guillemets | `"New York"` |
| Chaîne entre apostrophes | `'Lyon 3e'` |
| Booléen | `true`, `false` |
| Nul | `null` |
| Liste | `1,2,3`, `"A","B"` |

Règles :

- Une valeur non quotée se termine à l'espace, à la virgule ou à `}`.
- Une valeur contenant un espace, une virgule ou un opérateur doit être quotée.
- Les guillemets peuvent être échappés avec `\"`, `\'` et `\\`.
- Dans une liste, les virgules séparent les valeurs. Une virgule à l'intérieur de guillemets ne sépare pas.
- `true` et `false` sont normalisés en `1` et `0`.
- `null` est normalisé en `null`.

Exemples :

```text
{if field_city="New York"}
{if field_tags in "maison","appartement"}
{if field_price>=100000}
```

### 5.5 Variables disponibles

#### Variables de contexte

| Variable | Type | Description |
|---|---|---|
| `catid` | entier | ID de la catégorie principale de l'article. |
| `incat` | tableau d'entiers | IDs de toutes les catégories assignées à l'article. |
| `state_id` | entier | État numérique de l'article (`1`, `0`, `2`, `-3`, etc.). |
| `lang` | chaîne | Langue effective du template (`fr-FR`, `en-GB`, etc.). |
| `type_id` | entier | ID du type de contenu. |
| `item_id` | entier | ID de l'article. |
| `author_id` | entier | ID de l'auteur (`created_by`). |
| `editor_id` | entier | ID de l'utilisateur courant. |

#### Champs personnalisés

Un champ est accessible de deux manières :

- `field_<NOM>` : accès explicite au champ ;
- `<NOM>` : accès court si le nom n'entre pas en conflit avec une variable de contexte.

Le nom est normalisé comme pour les tags :

1. passage en majuscules ;
2. remplacement de tout caractère non alphanumérique (hors `_`) par `_`.

Exemple : un champ nommé `floors`, `floor-number` ou `Floor Number` est accessible via `FIELD_FLOORS`.

Résolution d'un identifiant nu :

1. variable de contexte (`catid`, `state_id`, etc.) ;
2. champ personnalisé normalisé ;
3. sinon `null`.

Ainsi, un champ nommé `title` ne masque pas la variable de contexte. Pour le tester, il faut écrire `field_title`.

#### Champs absents

Un champ non défini pour le type de contenu courant est traité comme `null`. Aucune erreur n'est générée.

### 5.6 Espaces et sauts de ligne

En v1, les marqueurs de condition sont supprimés, mais les espaces et sauts de ligne qui les entourent sont conservés.

Conséquence : un bloc écrit sur plusieurs lignes peut laisser une ligne vide après suppression.

Exemple :

```text
Début
{if catid=11}
Contenu
{/if}
Fin
```

Si la condition est fausse, le résultat peut contenir une ligne vide entre `Début` et `Fin`.

Les auteurs peuvent écrire les marqueurs en ligne pour contrôler précisément le rendu :

```text
Début {if catid=11}Contenu{/if} Fin
```

Une gestion plus fine des espaces (`{-if ...}`, `{/if-}`) pourra être ajoutée en v2.

### 5.7 Échappement

Il n'existe pas de séquence d'échappement officielle pour afficher littéralement `{if ...}` dans un template en v1.

Dans un corps HTML, il est possible d'utiliser des entités HTML :

```html
&#123;if catid=11&#125;
```

Dans un corps texte, il faut éviter d'écrire littéralement la séquence ou accepter qu'elle soit interprétée.

Les entités HTML sont décodées dans l'expression avant analyse. Un éditeur WYSIWYG qui enregistre `&gt;=`, `&lt;` ou `&quot;` à la place de `>=`, `<` et `"` ne casse donc pas les conditions :

```text
{if field_price&gt;=100000}   →  {if field_price>=100000}
{if field_floors&lt;10}        →  {if field_floors<10}
```

---

## 6. Sémantique d'évaluation

### 6.1 Contexte de condition

Le contexte est construit dans `ParentClassItem::sendNotificationEmails()` et transmis à `FlexicontentHelperNotificationMail::send()`.

Structure :

```php
$condition_context = [
    'catid'     => (int) $this->get('catid'),
    'incat'     => array_keys($after_cats),
    'state_id'  => (int) $this->get('state'),
    'lang'      => FlexicontentHelperNotificationMail::getLanguage((string) $this->get('language')),
    'type_id'   => (int) $this->get('type_id'),
    'item_id'   => (int) $this->get('id'),
    'author_id' => (int) $this->get('created_by'),
    'editor_id' => (int) $user->get('id'),
    'fields'    => [
        'FIELD_FLOORS' => [1, 2, 3],
        'FIELD_CITY'   => 'Lyon',
        'FIELD_PRICE'  => 250000,
    ],
];
```

Le contexte `fields` contient les **valeurs brutes** des champs, pas les valeurs aplaties utilisées pour les tags.

### 6.2 Normalisation des valeurs

Avant comparaison, chaque valeur est normalisée :

1. **Objet** : conversion via `flattenValue()` ; si l'objet est converti en tableau, il est traité comme un tableau.
2. **Tableau** : normalisation récursive de chaque élément, puis concaténation en liste plate de scalaires.
3. **Booléen** : `true` → `1`, `false` → `0`.
4. **Chaîne** :
   - décodage des entités HTML (`html_entity_decode`, UTF-8) ;
   - suppression des balises HTML (`strip_tags`) ;
   - trim des espaces en début et fin.
5. **Nombre** : conservé tel quel.
6. **Null** : conservé tel quel.

La normalisation ne modifie pas les valeurs stockées ni les tags `{TAG}`. Elle ne concerne que l'évaluation des conditions.

### 6.3 Vide et véracité

Deux notions distinctes sont utilisées.

#### `empty` (présence stricte)

Une valeur est **strictement vide** si elle est :

- `null` ;
- `''` après trim ;
- `[]` (tableau sans élément).

Ne sont **pas** vides :

- `0` ;
- `'0'` ;
- `false` (normalisé en `0`) ;
- `[0]` ;
- `' '` après trim devient `''` et est donc vide.

Pour un tableau :

- `empty` est vrai si le tableau est vide ou ne contient que des valeurs strictement vides ;
- la négation s'écrit `!variable empty` et est vraie si au moins un élément n'est pas strictement vide.

#### Véracité (syntaxe sans opérateur)

Une valeur est **truthy** si elle n'est pas falsy.

Sont **falsy** :

- `null` ;
- `''` ;
- `[]` ;
- `0` ;
- `'0'` ;
- `false` (normalisé en `0`).

Sont **truthy** :

- toute autre chaîne, y compris `'false'` ;
- tout nombre non nul ;
- tout tableau contenant au moins un élément truthy.

Conséquence importante :

- `{if field_featured}` est faux pour `0` ;
- `{if !field_price empty}` est vrai pour `0`.

Cette distinction permet de choisir entre « valeur significative » et « valeur simplement présente ».

### 6.4 Comparaisons

#### Égalité `=` et `!=`

- Si les deux opérandes sont numériques (`is_numeric` après normalisation), comparaison numérique.
- Sinon, comparaison de chaînes :
  - insensible à la casse (`mb_strtolower`, UTF-8) ;
  - accents conservés (`é` ≠ `e`) ;
  - espaces de début et fin supprimés.
- Si le champ est un tableau, `=` est vrai si **au moins un élément** égale la valeur.
- `!=` est la négation de `=`.

#### `in` et `not in`

- La partie droite est une liste de valeurs.
- Si le champ est un tableau, `in` est vrai si **au moins un élément** appartient à la liste.
- Sinon, `in` est vrai si la valeur appartient à la liste.
- `not in` est la négation de `in`.

#### `contains` et `not contains`

- Comparaison de sous-chaîne, insensible à la casse.
- Si le champ est un tableau, vrai si au moins un élément contient la sous-chaîne.
- La partie droite doit être une valeur unique, pas une liste.
- `not contains` est la négation de `contains`.

#### Comparaisons numériques `>`, `>=`, `<`, `<=`

- Les deux opérandes doivent être numériques après normalisation.
- Si l'un des deux n'est pas numérique, le résultat est faux.
- Pour un tableau, vrai si au moins un élément numérique satisfait la comparaison.
- Aucune erreur n'est générée si la comparaison n'est pas possible.

### 6.5 Champs multi-valeurs

Les valeurs de champs peuvent être :

- scalaire ;
- tableau simple (`[1, 2, 3]`) ;
- tableau imbriqué (relations, adresses, etc.) ;
- objet ou valeur sérialisée.

Avant évaluation, les tableaux et objets sont aplatis en une liste de scalaires par `flattenValue()`.

Règles :

- `=` et `in` utilisent une sémantique « au moins un élément » ;
- `contains` utilise une sémantique « au moins un élément contient » ;
- `empty` (et sa négation `!... empty`) porte sur la liste aplatie ;
- les comparaisons numériques portent sur les éléments numériques.

### 6.6 Champs inconnus

Un identifiant inconnu est résolu en `null`.

Conséquences :

- `{if champ_inconnu}` → faux ;
- `{if !champ_inconnu}` → vrai ;
- `{if champ_inconnu empty}` → vrai ;
- `{if !champ_inconnu empty}` → faux ;
- `{if champ_inconnu=valeur}` → faux ;
- `{if champ_inconnu!=valeur}` → vrai.

Un avertissement est ajouté au debug, mais l'envoi n'est pas bloqué.

### 6.7 Erreurs de syntaxe

Le système applique une stratégie **fail open** : en cas d'erreur, le contenu est conservé plutôt que supprimé.

| Cas | Comportement |
|---|---|
| `{if}` sans `{/if}` | Le marqueur `{if ...}` est supprimé, le contenu est conservé, avertissement debug. |
| `{/if}` sans `{if}` | Le marqueur est supprimé, avertissement debug. |
| `{else}` sans `{if}` | Le marqueur est supprimé, avertissement debug. |
| `{elseif}` sans `{if}` | Le marqueur est supprimé, avertissement debug. |
| Expression vide | Condition considérée vraie, contenu conservé, avertissement debug. |
| Opérateur inconnu | Condition considérée vraie, contenu conservé, avertissement debug. |
| Variable inconnue | Résolue en `null`, pas d'erreur bloquante. |
| Guillemet non fermé | Condition considérée vraie, contenu conservé, avertissement debug. |
| Bloc imbriqué trop profond | Le bloc est considéré vrai, contenu conservé, avertissement debug. |

Les avertissements ne sont visibles que si `nf_enable_debug` est activé.

### 6.8 Sujet ou corps vide après rendu

- Si le sujet rendu est vide, le sujet **non traité** est utilisé en secours, et un avertissement debug est ajouté.
- Si le corps texte rendu est vide mais que le corps HTML ne l'est pas, le comportement normal de `MailTemplate` s'applique.
- Si les deux corps sont vides, l'email est envoyé avec un corps vide et un avertissement debug est ajouté. Aucun blocage.

---

## 7. Pipeline de rendu

### 7.1 Ordre des opérations

1. `sendNotificationEmails()` construit :
   - le contexte d'affichage (`$template_data`) ;
   - le contexte de condition (`$condition_context`).
2. `FlexicontentHelperNotificationMail::send()` reçoit les deux contextes.
3. Le helper instancie `FlexicontentMailTemplate` et lui transmet le contexte de condition.
4. `FlexicontentMailTemplate::loadTemplate()` charge la ligne brute depuis `#__mail_templates`.
5. Les conditions sont évaluées sur `subject`, `body` et `htmlbody` **bruts**.
6. `MailTemplate::send()` poursuit :
   - traduction des clés de langue ;
   - remplacement des tags `{TAG}` ;
   - génération du corps HTML/texte ;
   - envoi.

### 7.2 Pourquoi évaluer sur le contenu brut

Les conditions sont évaluées dans `loadTemplate()` et non dans `replaceTags()`.

Raisons :

- les conditions sont évaluées avant l'insertion des valeurs de champs ;
- une valeur de champ contenant `{if ...}` ne peut pas être interprétée comme une condition ;
- le traitement est isolé du rendu final ;
- le comportement est indépendant de l'ordre des appels internes de `MailTemplate`.

Conséquence : les conditions doivent être écrites **dans le contenu du template** édité dans `com_mails`, pas dans un fichier de langue.

Les templates livrés par défaut, dont le corps est une clé de langue (`FLEXI_NF_MAIL_BODY_*`), ne contiennent pas de conditions. Dès que l'administrateur édite le template dans `com_mails`, le contenu littéral est stocké et les conditions sont prises en charge.

### 7.3 Sujet, corps texte et corps HTML

Les trois champs sont traités indépendamment :

- `subject` ;
- `body` (texte brut) ;
- `htmlbody` (HTML).

Les conditions doivent être cohérentes entre `body` et `htmlbody`. Si `htmlbody` est vide, Joomla peut le générer à partir de `body` ; les conditions de `body` s'appliquent alors normalement.

### 7.4 Fallback legacy

Si le système de templates Joomla est indisponible, le helper utilise le corps legacy construit en PHP. Ce corps ne contient pas de conditions et n'est pas concerné par cette spécification.

### 7.5 Compatibilité avec les versions de Joomla

`FlexicontentMailTemplate` étend `Joomla\CMS\Mail\MailTemplate` et surcharge `loadTemplate()`.

Au démarrage :

1. vérifier que la classe `MailTemplate` existe ;
2. vérifier que la méthode `loadTemplate` existe et est surchargeable ;
3. si ce n'est pas le cas, utiliser la classe de base et supprimer les marqueurs de condition du template pour éviter d'envoyer la syntaxe brute.

Un test de non-régression doit être exécuté à chaque montée de version majeure de Joomla.

---

## 8. Conception technique

### 8.1 Nouvelle classe `FlexicontentMailTemplate`

Fichier proposé : `admin/helpers/notificationmailtemplate.php`

```php
<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Mail\MailTemplate;

class FlexicontentMailTemplate extends MailTemplate
{
    /** @var array */
    protected $conditionContext = [];

    /** @var array */
    protected $conditionErrors = [];

    public function setConditionContext(array $context)
    {
        $this->conditionContext = $context;
    }

    public function getConditionErrors()
    {
        return $this->conditionErrors;
    }

    protected function loadTemplate(string $key, string $language): ?\stdClass
    {
        $mail = parent::loadTemplate($key, $language);

        if (!$mail || !$this->conditionContext)
        {
            return $mail;
        }

        foreach (['subject', 'body', 'htmlbody'] as $property)
        {
            if (!empty($mail->$property))
            {
                $mail->$property = FlexicontentHelperNotificationMail::renderConditions(
                    $mail->$property,
                    $this->conditionContext,
                    $this->conditionErrors
                );
            }
        }

        return $mail;
    }
}
```

La classe est enregistrée avec `JLoader::register()`.

### 8.2 Modification de `send()`

Signature actuelle :

```php
public static function send($type, $recipients, array $data, $language = '', $sendAsBcc = false)
```

Nouvelle signature :

```php
public static function send(
    $type,
    $recipients,
    array $data,
    $language = '',
    $sendAsBcc = false,
    array $conditionContext = []
)
```

Si `$conditionContext` est vide, le comportement reste identique à l'existant.

### 8.3 Nouvelles méthodes du helper

| Méthode | Rôle |
|---|---|
| `renderConditions($text, array $context, array &$errors = [])` | Parse et rend les blocs conditionnels d'un texte. |
| `getFieldConditionContext(array $fields)` | Construit le tableau `fields` du contexte à partir des objets champs. |
| `normalizeFieldName($name)` | Normalise un nom de champ comme les tags. |
| `normalizeConditionValue($value)` | Normalise une valeur pour comparaison. |
| `isEmptyConditionValue($value)` | Implémente `empty`. |
| `isTruthyConditionValue($value)` | Implémente la véracité. |
| `compareConditionValues($left, $operator, $right)` | Évalue une comparaison. |
| `resolveConditionOperand($name, array $context)` | Résout une variable ou un champ. |

### 8.4 Construction du contexte dans le modèle

Dans `ParentClassItem` :

```php
$fields = $this->getExtrafields(true);

$template_data = array_merge(
    $template_data,
    FlexicontentHelperNotificationMail::getFieldTags($fields)
);

$condition_context = [
    'catid'     => (int) $this->get('catid'),
    'incat'     => array_keys($after_cats),
    'state_id'  => (int) $this->get('state'),
    'lang'      => FlexicontentHelperNotificationMail::getLanguage((string) $this->get('language')),
    'type_id'   => (int) $this->get('type_id'),
    'item_id'   => (int) $this->get('id'),
    'author_id' => (int) $this->get('created_by'),
    'editor_id' => (int) $user->get('id'),
    'fields'    => FlexicontentHelperNotificationMail::getFieldConditionContext($fields),
];
```

Afin d'éviter un double chargement des champs, `getNotificationFieldTags()` peut être remplacé par une méthode retournant à la fois les tags et le contexte.

### 8.5 Algorithme du parseur

Le parseur doit :

1. scanner le texte et produire des tokens :
   - texte brut ;
   - ouverture `{if ...}` ;
   - `{elseif ...}` ;
   - `{else}` ;
   - fermeture `{/if}` ;
2. construire un arbre de nœuds :
   - nœud texte ;
   - nœud conditionnel avec une liste de branches (`condition`, `nodes`) et une branche `else` ;
3. évaluer l'arbre avec le contexte ;
4. concaténer le texte des branches retenues.

Contraintes du scanner :

- reconnaître les accolades même si elles sont entourées d'espaces ;
- respecter les guillemets pour ne pas couper sur `}` ou `,` à l'intérieur d'une chaîne ;
- être insensible à la casse pour les mots-clés ;
- ne pas utiliser `eval()`.

Pseudo-code :

```text
function renderConditions(text, context, errors):
    tokens = tokenize(text)
    ast = parse(tokens)
    return evaluate(ast, context, errors)
```

### 8.6 Limites de sécurité et de performance

| Limite | Valeur proposée |
|---|---|
| Taille maximale d'un template traité | 1 Mo |
| Profondeur maximale d'imbrication | 10 |
| Nombre maximal de blocs conditionnels par template | 500 |
| Longueur maximale d'une expression | 1 000 caractères |
| Nombre maximal d'avertissements conservés | 50 |

En cas de dépassement :

- arrêter l'évaluation des conditions ;
- conserver le texte brut ;
- ajouter un avertissement debug ;
- ne jamais bloquer l'envoi.

### 8.7 Cache

Optionnel en v1.

Si nécessaire, mettre en cache l'AST par couple :

```text
hash(template_id + language + subject/body/htmlbody)
```

Le contexte n'entre pas dans la clé de cache, car il ne modifie pas l'AST.

Un cache statique par requête suffit. Pas de cache persistant en v1.

---

## 9. Référence d'écriture

### 9.1 Catégorie principale

```text
{if catid=11}
Contenu réservé aux actualités.
{/if}
```

### 9.2 Catégories multiples

```text
{if incat in 11,12}
Contenu pour les actualités ou les événements.
{/if}
```

### 9.3 Champ vide

```text
{if field_floors}
Étage : {FIELD_FLOORS}
{/if}
```

### 9.4 Champ vide avec alternative

```text
{if field_description}
{FIELD_DESCRIPTION}
{else}
Aucune description fournie.
{/if}
```

### 9.5 Champ booléen

```text
{if field_featured=1}
À la une
{/if}
```

### 9.6 Champ multi-valeurs

```text
{if field_equipment in "piscine","garage"}
Équipements disponibles.
{/if}
```

### 9.7 Comparaison numérique

```text
{if field_price>=100000}
Bien haut de gamme.
{/if}
```

### 9.8 Combinaison

```text
{if catid=11 and field_price>=100000}
Annonce premium dans les actualités.
{/if}
```

### 9.9 Négation

```text
{if !field_phone}
Aucun téléphone renseigné.
{/if}
```

### 9.10 `elseif`

```text
{if catid=11}
Actualités
{elseif catid=18}
Événements
{else}
Autre catégorie
{/if}
```

### 9.11 Anti-patterns

À éviter :

- conditions très longues avec de nombreux `and`/`or` ;
- blocs qui coupent une balise HTML ;
- conditions sur des champs dont le nom est ambigu (`title`) sans préfixe `field_` ;
- duplication de la même condition dans plusieurs blocs ;
- utilisation de `{if}` pour masquer des informations critiques.

---

## 10. Débogage

Lorsque `nf_enable_debug` est activé, ajouter au message de debug :

- la liste des conditions évaluées et leur résultat ;
- les variables inconnues ;
- les erreurs de syntaxe ;
- les branches sélectionnées ;
- le contexte de catégorie (`catid`, `incat`) et l'état (`state_id`).

Exemple de sortie :

```text
CONDITIONS
- [ligne 4] catid=11 => true
- [ligne 8] field_floors => false (field vide)
- [ligne 12] field_price>=100000 => false (valeur non numérique)
```

Les erreurs doivent être dédupliquées et limitées à 50 entrées.

---

## 11. Sécurité

- Aucun `eval()`, aucune fonction PHP appelée depuis une expression.
- Les variables et opérateurs sont limités à une liste blanche.
- Les conditions sont évaluées uniquement sur le contenu du template, jamais sur les valeurs de champs.
- Les valeurs de champs ne sont jamais interprétées comme du code ou comme des conditions.
- Les limites de taille, de profondeur et de nombre de blocs protègent contre les templates pathologiques.
- L'édition des templates reste protégée par les droits de `com_mails`.
- Les conditions ne permettent pas d'accéder à des données non déjà exposées par les tags.

---

## 12. Compatibilité et migration

- Aucune migration de base de données.
- Aucun changement pour les templates existants sans `{if`.
- Les conditions sont une extension de la syntaxe des templates, pas un nouveau type de template.
- Les templates créés avant cette fonctionnalité restent valides.
- Si un template contient déjà littéralement `{if ...}` dans un but non conditionnel, il sera désormais interprété. Risque faible, à documenter dans les notes de version.
- Si `FlexicontentMailTemplate` ne peut pas être utilisée, les marqueurs de condition sont supprimés et le contenu est conservé.

---

## 13. Plan de tests

### 13.1 Tests unitaires du parseur

Le script `_tools/tests/notificationmail-conditions.php` couvre ces cas. Il s'exécute sans bootstrap Joomla :

```bash
php administrator/components/com_flexicontent/_tools/tests/notificationmail-conditions.php
```

- texte sans condition ;
- condition vraie ;
- condition fausse ;
- `else` ;
- `elseif` ;
- imbrication ;
- `and`, `or`, `!` ;
- guillemets simples et doubles ;
- virgules dans les listes ;
- accolades dans les valeurs ;
- blocs non fermés ;
- marqueurs orphelins ;
- expression vide ;
- opérateur inconnu ;
- profondeur maximale ;
- taille maximale.

### 13.2 Tests de résolution du contexte

- `catid` ;
- `incat` avec plusieurs catégories ;
- `state_id` ;
- `lang` ;
- champ scalaire ;
- champ tableau ;
- champ tableau imbriqué ;
- champ absent ;
- champ nommé comme une variable de contexte ;
- champ nommé `title` ;
- valeur `0` ;
- valeur `'0'` ;
- valeur vide ;
- valeur HTML.

### 13.3 Tests d'intégration

Pour chaque scénario de notification :

- nouveau contenu ;
- nouveau contenu en attente ;
- contenu modifié ;
- contenu modifié avec revue de version ;
- demande d'approbation.

Vérifier :

- sujet conditionnel ;
- corps texte conditionnel ;
- corps HTML conditionnel ;
- absence de template dédié par catégorie ;
- fallback global ;
- fallback legacy ;
- mode debug activé/désactivé ;
- envoi réel via DDEV.

### 13.4 Tests de sécurité

- un champ contenant `{if catid=11}` ne doit pas être interprété ;
- un champ contenant `{/if}` ne doit pas casser le rendu ;
- un template volumineux ne doit pas bloquer l'envoi ;
- une imbrication excessive ne doit pas provoquer de boucle infinie.

### 13.5 Tests de non-régression

- template sans condition : sortie identique à avant ;
- tags `{TAG}` toujours remplacés ;
- valeurs échappées dans le corps HTML via `addUnsafeTags()` ;
- compatibilité `com_mails` : édition, sauvegarde, aperçu ;
- `php -l`, `xmllint`, parse INI.

---

## 14. Documentation utilisateur

Mettre à jour :

- `FLEXI_NF_MAIL_TEMPLATES_INFO` avec un résumé de la syntaxe et un exemple ;
- la description des templates dans `com_mails` si nécessaire ;
- ce document de spécification ;
- éventuellement une page d'aide Joomla.

Le texte d'aide doit rester court et renvoyer vers des exemples. Il ne doit pas contenir l'intégralité de la grammaire.

---

## 15. Critères d'acceptation

La fonctionnalité est acceptée si :

1. un template sans condition produit exactement la même sortie qu'avant ;
2. les conditions fonctionnent dans le sujet, le corps texte et le corps HTML ;
3. `catid`, `incat`, `state_id`, `lang` et les champs sont disponibles ;
4. `if`, `elseif`, `else`, l'imbrication et les opérateurs documentés fonctionnent ;
5. les champs vides, `0`, `'0'`, tableaux et champs absents respectent la sémantique définie ;
6. une condition invalide ne bloque jamais l'envoi ;
7. les valeurs de champs ne sont jamais interprétées comme des conditions ;
8. le mode debug affiche les conditions évaluées et les erreurs ;
9. les tests unitaires et d'intégration passent ;
10. la documentation est à jour ;
11. `php -l`, `xmllint` et le parse INI passent.

---

## 16. Phasage

### Phase 1 — Socle

- parseur et AST ;
- `{if}`, `{elseif}`, `{else}`, `{/if}` ;
- imbrication ;
- contexte `catid`, `incat`, `state_id`, `lang` ;
- champs ;
- opérateurs de présence, `=`, `!=`, `empty` (négation via `!`), `in`, `not in`, `contains`, `not contains` ;
- comparaisons numériques ;
- `and`, `or`, `!` ;
- erreurs fail open ;
- debug.

### Phase 2 — Confort

- cache d'AST ;
- aide utilisateur enrichie ;
- aperçu du rendu conditionnel dans `com_mails`, si cela reste réalisable sans contexte d'article.

### Phase 3 — Extensions éventuelles

- `starts with`, `ends with` ;
- parenthèses ;
- gestion fine des espaces ;
- conditions sur d'autres variables (groupes d'utilisateurs, champs de l'auteur, etc.).

---

## 17. Annexe — Grammaire EBNF

```ebnf
template    = { text | block } ;
block       = "{" ws "if" ws expression ws "}" template
              { "{" ws "elseif" ws expression ws "}" template }
              [ "{" ws "else" ws "}" template ]
              "{" ws "/if" ws "}" ;
expression  = or_expr ;
or_expr     = and_expr { ws ( "or" | "||" ) ws and_expr } ;
and_expr    = unary { ws ( "and" | "&&" ) ws unary } ;
unary       = [ "!" | "not" ws ] predicate ;
predicate   = operand [ ws operator [ ws value_list ] ] ;
operand     = identifier ;
operator    = "=" | "==" | "!="
            | "in" | "not" ws "in"
            | "contains" | "not" ws "contains"
            | "empty"
            | ">" | ">=" | "<" | "<=" ;
value_list  = value { ws "," ws value } ;
value       = quoted | bare | number | "true" | "false" | "null" ;
quoted      = '"' { escaped | any_char_except_quote_backslash } '"'
            | "'" { escaped | any_char_except_apostrophe_backslash } "'" ;
bare        = bare_char { bare_char } ;
identifier  = ( letter | "_" ) { letter | digit | "_" } ;
ws          = { " " | "\t" | "\r" | "\n" } ;
```

Notes :

- `empty` n'a pas de partie droite. Sa négation s'écrit avec `!` devant l'opérande : `!variable empty`.
- Les mots-clés sont insensibles à la casse.
- Les parenthèses ne font pas partie de la grammaire v1.
- Les valeurs littérales `true` et `false` sont converties en `1` et `0`.

---

## 18. Annexe — Matrice de tests rapide

| Cas | Expression | Contexte | Attendu |
|---|---|---|---|
| Catégorie principale | `{if catid=11}` | `catid=11` | affiché |
| Catégorie principale | `{if catid=11}` | `catid=12` | masqué |
| Catégories multiples | `{if incat in 11,12}` | `incat=[18,12]` | affiché |
| Champ vide | `{if field_floors}` | `fields.floors=null` | masqué |
| Champ zéro | `{if field_floors}` | `fields.floors=0` | masqué |
| Champ zéro présent | `{if !field_floors empty}` | `fields.floors=0` | affiché |
| Égalité | `{if field_city=Lyon}` | `fields.city="lyon"` | affiché |
| Inégalité | `{if field_city!=Lyon}` | `fields.city="Paris"` | affiché |
| Liste | `{if field_tags in "a","b"}` | `fields.tags=["b","c"]` | affiché |
| Contient | `{if field_text contains "piscine"}` | `fields.text="Belle piscine"` | affiché |
| Numérique | `{if field_price>=100}` | `fields.price=150` | affiché |
| Numérique invalide | `{if field_price>=100}` | `fields.price="cher"` | masqué |
| Négation | `{if !field_phone}` | `fields.phone=""` | affiché |
| `else` | `{if field_phone}A{else}B{/if}` | `fields.phone=""` | `B` |
| `elseif` | `{if catid=11}A{elseif catid=18}B{else}C{/if}` | `catid=18` | `B` |
| Imbrication | `{if catid=11}{if field_x=1}A{/if}{/if}` | `catid=11, x=1` | `A` |
| Champ inconnu | `{if field_unknown}` | — | masqué |
| Condition invalide | `{if catid=}` | — | contenu conservé |
| Valeur utilisateur piégée | champ = `{if catid=11}` | — | non interprété |

---

## 19. Décisions tranchées

Les points suivants ont été arbitrés avant l'implémentation :

1. **`notempty` n'est pas un opérateur.** Seul `empty` existe, et sa négation s'écrit `{if !variable empty}`.
2. **`elseif` fait partie de la phase 1.**
3. **Les comparaisons de chaînes sont insensibles à la casse.** Aucun opérateur `===` sensible à la casse n'est prévu.
4. **La variable globale `fields` n'est pas exposée.** Seuls les champs individuels sont accessibles, via `field_<nom>` ou leur nom nu.
5. **Aucune option globale de désactivation.** Le fail open suffit.
6. **Un aperçu du rendu conditionnel dans `com_mails` est souhaité.** Il n'est pas réalisable de façon fiable en phase 1 : sans article de référence, les conditions sur `catid`, `incat` et les champs ne peuvent pas être évaluées. Il est renvoyé en phase 2, avec un sélecteur d'article servant de contexte.
7. **Les conditions ne s'appliquent pas aux noms de pièces jointes.**

---

## 20. Résumé

La proposition consiste à étendre la syntaxe des templates avec des blocs `{if ...}` évalués avant le remplacement des tags Joomla.

Elle permet de couvrir les besoins de personnalisation par catégorie et par champ sans créer de templates supplémentaires, tout en gardant `com_mails` comme point d'édition unique.

Le point critique est l'évaluation sur le **contenu brut du template**, avant l'insertion des valeurs de champs, afin d'éviter toute interprétation de données utilisateur comme du code de template.
