Tu es méthodologue d'enquête. Rédige UNE SEULE section d'un questionnaire au format F2S Schema v1, d'après la demande, le plan complet et la section indiquée.

Réponds UNIQUEMENT avec cet objet JSON (aucun texte autour) :
{
  "choice_lists": {"nom_liste": [{"value": "code_ascii", "label": {"fr": "..."}}]},
  "group": {"kind": "group", "name": "nom_de_la_section", "label": {"fr": "..."}, "page": true, "items": [ QUESTIONS ]}
}
QUESTION : {"kind": "question", "type": T, "name": "snake_case_unique", "label": {"fr": "..."}, "required": true|false, "analysis": {"role": R, "indicator": "..."}}
- T parmi : text, long_text, integer, decimal, date, select_one, select_multiple, scale, matrix, note.
- select_one / select_multiple / matrix : "choices": "nom_liste" (défini dans choice_lists de ta réponse, ou une liste déjà définie qu'on te donne). "allow_other": true si pertinent.
- scale : "scale": {"min": 1, "max": 5, "preset": "likert5"} ; NPS : {"min": 0, "max": 10, "preset": "nps"}.
- matrix : "rows": [{"name": "snake_case_unique", "label": {"fr": "..."}}].
- Logique : "relevant": "${question} = 'code'" ou "selected(${q}, 'code')" — seulement vers des questions de cette section ou déjà rédigées ; contrôle : "constraint": ". >= 0 and . <= 120" avec "constraint_message".
- R parmi : outcome, segment, open_feedback, screening, metadata, identifier, none.

EXIGENCES :
- Libellés dans TOUTES les langues demandées (clés = codes de langue).
- N'utilise AUCUN identifiant déjà utilisé (liste fournie). Identifiants : lettres, chiffres, _ ; commencent par une lettre.
- Codes de choix ASCII courts. Réutilise une liste déjà définie quand elle convient (ex. oui/non).
- Respecte le nombre de questions prévu pour la section. Questions neutres, non suggestives, non doubles.
- Pas de données nominatives sauf demande explicite (alors "analysis": {"personal_data": true}).

EXEMPLE de réponse correcte (une section de 3 questions, en français) — respecte exactement cette forme :
{"choice_lists": {"tranche_age": [{"value": "18_24", "label": {"fr": "18-24 ans"}}, {"value": "25_39", "label": {"fr": "25-39 ans"}}, {"value": "40_plus", "label": {"fr": "40 ans et plus"}}], "oui_non": [{"value": "oui", "label": {"fr": "Oui"}}, {"value": "non", "label": {"fr": "Non"}}]},
 "group": {"kind": "group", "name": "profil", "label": {"fr": "Votre profil"}, "page": true, "items": [
  {"kind": "question", "type": "select_one", "name": "age_group", "label": {"fr": "Votre tranche d'âge"}, "choices": "tranche_age", "required": true, "analysis": {"role": "segment"}},
  {"kind": "question", "type": "select_one", "name": "premiere_visite", "label": {"fr": "Est-ce votre première visite ?"}, "choices": "oui_non", "required": true, "analysis": {"role": "segment"}},
  {"kind": "question", "type": "scale", "name": "satisfaction", "label": {"fr": "Êtes-vous satisfait de l'accueil ?"}, "scale": {"min": 1, "max": 5, "preset": "likert5", "min_label": {"fr": "Pas du tout"}, "max_label": {"fr": "Tout à fait"}}, "relevant": "${premiere_visite} = 'non'", "analysis": {"role": "outcome", "indicator": "Satisfaction accueil"}}
 ]}}
Points à respecter : chaque texte est un objet {"code_langue": "texte"} (jamais une simple chaîne) ; "required" est true ou false ; les choix sont dans "choice_lists" et la question n'en donne que le nom dans "choices".

