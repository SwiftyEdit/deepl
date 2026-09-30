---
title: DeepL - Übersicht
description: Automatische Übersetzungen mit DeepL
btn: Übersicht
group: addons
priority: 10
---

# DeepL

Das Plugin übersetzt Seiten und Produkte mit der DeepL API - direkt beim Speichern,
in alle verknüpften Sprachversionen.

## Einrichtung

1. Ein Konto bei [DeepL API](https://www.deepl.com/pro-api) anlegen (die kostenlose
   Variante „DeepL API Free“ reicht für 500.000 Zeichen im Monat).
2. Unter **Einstellungen** den API-Schlüssel speichern. Er wird vorher bei DeepL
   geprüft. Free- und Pro-Schlüssel werden automatisch erkannt.
3. Das Plugin unter **Addons** aktivieren - Hooks werden nur für aktive Plugins geladen.

Auf der **Übersicht** siehst du danach das verbrauchte Kontingent, kannst eine
Test-Übersetzung machen und findest die letzten Übersetzungen.

## Übersetzen

Die Übersetzung arbeitet mit den **Übersetzungs-URLs** einer Seite bzw. eines Produkts:

1. Die Seite (oder das Produkt) in jeder Sprache einmal anlegen.
2. In der Ausgangsseite unter *Übersetzungen (URLs)* den Permalink jeder Sprachversion
   eintragen. Bei **Produkten** ist das die Katalogseite + Slug, also genau der Pfad, unter dem
   das Produkt im Frontend erreichbar ist, z. B. `en/shop/shoe-max/` - der Slug allein reicht
   nicht, weil er in mehreren Katalogen vorkommen kann. Eine komplette URL mit Domain
   funktioniert auch.
3. Beim Speichern im Tab mit den Hooks die gewünschte **DeepL**-Aktion anhaken.

Nach dem Speichern werden alle verknüpften Sprachversionen mit der Übersetzung
**überschrieben**. Die Hooks sind bei jedem Speichern wieder abgehakt - es wird nur
übersetzt, wenn du es ausdrücklich auswählst.

### Einzelne Sprachen ausschließen

Soll eine Seite oder ein Produkt nur in bestimmte Sprachen übersetzt werden (z. B. weil es
eine Sprachversion inhaltlich noch nicht geben soll), hake im Tab **Addons** des Editors
unter *DeepL: nicht übersetzen nach* die Sprachen an, die übersprungen werden sollen.
Die Übersetzungs-URL bleibt dabei erhalten - Sprachumschalter und hreflang funktionieren
weiter. Die Auswahl wird mit der Seite bzw. dem Produkt gespeichert und gilt bei jedem
weiteren Speichern mit einem DeepL-Hook.

Sprachen ohne eingetragene Übersetzungs-URL sind dort mit *(keine Übersetzungs-URL)*
markiert - sie werden ohnehin nicht übersetzt.

### Seiten

*DeepL: Seite in alle verknüpften Sprachen übersetzen* übersetzt Titel, Linkname,
Meta-Beschreibung, Meta-Keywords und den Inhalt.

Nutzt eine **Zielseite** einen Block-Editor, wird sie auf HTML umgestellt - sonst würde
der Editor beim nächsten Speichern wieder die alten, unübersetzten Blöcke laden.
Nach jeder Übersetzung wird von der Zielseite ein Eintrag in der Versionsgeschichte angelegt.

### Produkte

| Aktion | Felder |
|---|---|
| komplett | alle unten genannten plus die Labels für Merkmale, Zubehör, ähnliche Produkte und Add-ons |
| Titel & Teaser | Titel, Teaser |
| Beschreibungen | Text, Zusatztexte 1-5 inkl. Labels, Lieferumfang |
| Meta-Daten | Meta-Titel, Meta-Beschreibung, Tags |

Preise, Bilder, Varianten und alle anderen Felder bleiben unverändert.

## Was wird nicht übersetzt?

- Elemente mit dem Attribut `translate="no"`
- Inhalte der HTML-Tags aus der Einstellung *Nicht übersetzen: HTML-Tags*
  (Standard: `code, pre, kbd, samp, script, style`)
- Shortcodes wie `[snippet]…[/snippet]` oder `[plugin=…]…[/plugin]`, solange
  *Shortcodes schützen* aktiv ist

## Einstellungen

- **Zielsprachen** - in welche SwiftyEdit-Sprachen die Hooks übersetzen dürfen.
  Daneben steht jeweils der DeepL-Sprachcode.
- **Englisch / Portugiesisch** - DeepL braucht bei diesen Sprachen eine regionale Variante.
- **Anrede** - förmlich oder informell, sofern die Zielsprache es unterstützt.
