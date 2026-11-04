"""Builds the reference import files with openpyxl (an independent writer, so the
PHP XlsxReader is tested on files it did not produce). Run: python3 build_fixtures.py"""
from pathlib import Path
from openpyxl import Workbook

HERE = Path(__file__).parent

def sheet(wb, title, rows, first=False):
    ws = wb.active if first else wb.create_sheet(title)
    ws.title = title
    for r in rows:
        ws.append(r)

wb = Workbook()
sheet(wb, 'survey', [
    ['type', 'name', 'label::Français (fr)', 'label::English (en)', 'hint::Français (fr)', 'required', 'relevant', 'constraint', 'constraint_message::Français (fr)', 'appearance', 'calculation', 'parameters', 'media::image', 'bind::odk:length'],
    ['start', 'start', None, None, None, None, None, None, None, None, None, None, None, None],
    ['end', 'end', None, None, None, None, None, None, None, None, None, None, None, None],
    ['deviceid', 'deviceid', None, None, None, None, None, None, None, None, None, None, None, None],
    ['begin_group', 'profil', 'Profil du répondant', 'Respondent profile', None, None, None, None, None, 'field-list', None, None, None, None],
    ['select_one sexe', 'sexe', 'Sexe', 'Sex', None, 'yes', None, None, None, None, None, None, None, None],
    ['integer', 'age', 'Âge', 'Age', 'En années révolues', 'TRUE', None, '. >= 15 and . <= 99', 'Entre 15 et 99 ans', None, None, None, 'age.png', None],
    ['text', 'nom-complet', 'Nom complet', 'Full name', None, 'no', None, None, None, None, None, None, None, '80'],
    ['end_group', None, None, None, None, None, None, None, None, None, None, None, None, None],
    ['begin group', 'services', 'Services', 'Services', None, None, "${age} >= 18", None, None, None, None, None, None, None],
    ['select_multiple services_list', 'services_utilises', 'Services utilisés', 'Services used', None, 'yes', None, None, None, None, None, None, None, None],
    ['select_one satisf or_other', 'satisfaction', 'Êtes-vous satisfait ?', 'Are you satisfied?', None, 'yes', "selected(${services_utilises}, 'pharmacie')", None, None, None, None, None, None, None],
    ['range', 'note_accueil', "Note de l'accueil", 'Welcome rating', None, None, None, None, None, None, None, 'start=0 end=10 step=1', None, None],
    ['text', 'commentaire', 'Commentaire', 'Comment', None, None, "${nom-complet} != ''", None, None, 'multiline', None, None, None, None],
    ['end group', None, None, None, None, None, None, None, None, None, None, None, None, None],
    ['note', 'merci', 'Merci pour votre participation', 'Thank you', None, None, None, None, None, None, None, None, None, None],
    ['calculate', 'age_mois', None, None, None, None, None, None, None, None, '${age} * 12', None, None, None],
    ['geopoint', 'position', 'Position', 'Location', None, None, None, None, None, None, None, None, None, None],
    ['rank', 'priorites', 'Classez vos priorités', 'Rank priorities', None, None, None, None, None, None, None, None, None, None],
    ['begin_repeat', 'enfants', 'Enfants', 'Children', None, None, None, None, None, None, None, None, None, None],
    ['text', 'prenom_enfant', "Prénom de l'enfant", 'Child first name', None, None, None, None, None, None, None, None, None, None],
    ['end_repeat', None, None, None, None, None, None, None, None, None, None, None, None, None],
    ['integer', 'age', 'Âge (doublon)', 'Age (duplicate)', None, None, None, None, None, None, None, None, None, None],
], first=True)
sheet(wb, 'choices', [
    ['list_name', 'name', 'label::Français (fr)', 'label::English (en)'],
    ['sexe', 'f', 'Femme', 'Female'],
    ['sexe', 'm', 'Homme', 'Male'],
    ['services_list', 'pharmacie', 'Pharmacie', 'Pharmacy'],
    ['services_list', 'labo', 'Laboratoire', 'Laboratory'],
    ['services_list', 'consult', 'Consultation', 'Consultation'],
    ['satisf', 'tres satisfait', 'Très satisfait', 'Very satisfied'],
    ['satisf', 'pas satisfait', 'Pas satisfait', 'Not satisfied'],
    ['satisf', 'pas satisfait', 'Doublon', 'Duplicate'],
    ['inutile', 'x', 'Liste inutilisée', 'Unused list'],
])
sheet(wb, 'settings', [
    ['form_title', 'form_id', 'version', 'default_language', 'style'],
    ['Enquête santé Douala', 'sante_douala', '2026110401', 'Français (fr)', 'pages'],
])
wb.save(HERE / 'kobo-xlsform.xlsx')

wb = Workbook()
sheet(wb, 'Questionnaire', [
    ['Section', 'Question', 'Type', 'Choix', 'Obligatoire', 'Aide', 'Remarques internes'],
    ['Profil', 'Votre tranche d\'âge', 'choix unique', '18-24 ans; 25-39 ans; 40 ans et plus', 'oui', None, 'à valider'],
    ['Profil', 'Votre quartier', None, 'Akwa | Bonapriso | Autre', 'oui', None, None],
    ['Profil', 'Nombre d\'enfants', 'nombre', None, None, 'Mettre 0 si aucun', None],
    ['Expérience', 'Recommanderiez-vous la pharmacie ?', 'NPS', None, 'oui', None, None],
    ['Expérience', 'Avez-vous trouvé le produit cherché ?', 'oui/non', None, None, None, None],
    ['Expérience', 'Quels services utilisez-vous ?', 'choix multiples', 'Conseil; Vente; Livraison', None, None, None],
    ['Expérience', 'Vos suggestions', 'texte long', None, None, None, None],
    [None, None, None, None, None, None, 'ligne sans question'],
    ['Expérience', 'Code promo', 'curseur 3D', None, None, None, None],
], first=True)
sheet(wb, 'Notes', [['Ceci est une feuille de notes']])
wb.save(HERE / 'questions-fr.xlsx')

csv = "﻿question;type;choix;obligatoire\nVotre ville;texte;;oui\nVotre âge;entier;;oui\nSatisfaction globale;échelle;;\nMoyen de paiement;choix unique;Espèces;Mobile Money;Carte\n"
# The last line shows a mis-quoted CSV: extra columns beyond the header are not kept.
(HERE / 'questions-utf8.csv').write_text(csv, encoding='utf-8')
(HERE / 'questions-cp1252.csv').write_bytes("question,type,choix\nRégion,choix unique,Littoral|Centre|Ouest\nRemarques générales,texte long,\n".encode('cp1252'))
print('fixtures written')
