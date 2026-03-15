# Reservierungen

Dieses Plugin erweitert MyBB um ein flexibles Reservierungssystem. Es ermöglicht, Reservierungen für verschiedene Kategorien - beispielsweise Avatarpersonen, Nachnamen, Canon-Charaktere oder andere forumsspezifische Inhalte - zentral zu verwalten. Reservierungen laufen automatisch nach einer festgelegten Frist ab und werden selbstständig entfernt, sodass das Team keine manuelle Kontrolle oder Pflege übernehmen muss.<br>
<br>
Das System ist vollständig kategoriebasiert aufgebaut. Das Team kann beliebig viele Reservierungskategorien anlegen, die jeweils unabhängig voneinander funktionieren. Dadurch lassen sich unterschiedliche Arten von Reservierungen parallel verwalten.<br>
Ein besonderer Fokus liegt auf der Flexibilität der Regeln für Reservierungen. Für jede Kategorie können individuelle Bedingungen definiert werden, die sich nach den jeweiligen Nutzergruppen richten. So lässt sich beispielsweise festlegen, wie viele Reservierungen jemand gleichzeitig haben darf, wie lange diese gültig sind, ob und wie oft sie verlängert werden können oder ob nach Ablauf eine temporäre Sperre greift. Dadurch lassen sich unterschiedliche Berechtigungen für zum Beispiel Teammitglieder, Bewerber:innen oder reguläre User:innen problemlos abbilden.<br> 
In den Plugin-Einstellungen festgelegt werden, ob für die Reservierungsbedingungen die primäre Nutzergruppe oder die sekundäre Nutzergruppe eines Accounts berücksichtigt werden sollen. Wird die Prüfung über sekundäre Gruppen aktiviert, werden vorhandene sekundäre Gruppen zuerst ausgewertet. Existiert für eine dieser Gruppen eine passende Regel, wird diese angewendet. Falls keine passende Regel vorhanden ist oder keine sekundären Gruppen gesetzt sind, greift automatisch die Regel der primären Nutzergruppe. Diese Einstellung ermöglicht eine besonders flexible Rechteverwaltung, beispielsweise wenn Teammitglieder zusätzlich eine andere primäre Gruppe im Forum besitzen, aber für Reservierungen dennoch die erweiterten Team-Berechtigungen gelten sollen.<br>
<br>
Zusätzlich kann für jede Kategorie eine automatische Überprüfung aktiviert werden. Dabei kann festgelegt werden, ob die eingetragene Reservierung mit einem bestimmten Profilfeld/Steckbrieffeld verglichen werden soll oder ob ein Abgleich mit bereits registrierten Accountnamen erfolgt. Beim Vergleich mit Accountnamen kann gewählt werden, ob der komplette Name, nur der Vorname (alles vor dem ersten Leerzeichen) oder nur der Nachname (alles nach dem ersten Leerzeichen) berücksichtigt werden soll.<br>
Für einzelne Kategorien kann außerdem optional eine Geschlechtsangabe aktiviert werden. In diesem Fall erscheint beim Eintragen einer Reservierung ein zusätzliches Auswahlfeld. Die verfügbaren Optionen für diese Auswahl können in den Plugin-Einstellungen definiert werden.<br>
<br>
Darüber hinaus besteht die Möglichkeit, eine Kategorie speziell für Gesuche zu kennzeichnen. Wird diese Option aktiviert, erscheint im Formular ein zusätzliches Feld, in dem ein Link zu einem entsprechenden Gesuchsthema angegeben werden muss. Dieser Link wird anschließend zusammen mit der Reservierung ausgegeben.<br>
<br>
Jede Kategorie wird grundsätzlich separat ausgegeben im Forum. Innerhalb einer Kategorie kann jedoch festgelegt werden, ob alle Reservierungen als eine fortlaufende Liste dargestellt werden oder ob sie nach den definierten Gruppen unterteilt angezeigt werden sollen.<br>
Die Gruppen orientieren sich dabei an den zuvor festgelegten Regeln für die verschiedenen Nutzergruppen. Für die Darstellung besteht zusätzlich die Möglichkeit, mehrere dieser Gruppen zusammenzufassen. So können beispielsweise getrennte Regelungen für Team, Bewerber:innen und User:innen bestehen, während in der Anzeige Bewerber:innen und User:innen gemeinsam in einer Gruppe ausgegeben werden. Die jeweiligen Berechtigungen und Reservierungsbedingungen bleiben dabei unverändert bestehen - die Zusammenfassung betrifft ausschließlich die Darstellung.<br>
Es kann auch ausgewählt werden in den Plugin-Einstellungen, ob die Kategorien in Tabs oder untereinander ausgegeben werden sollen.<br>
<br>
Ein besonderes Merkmal des Plugins ist die flexible Ausgabe im Forum. Das Team kann wählen, ob die Reservierungen über eine eigene Seite verwaltet werden oder über ein bestimmtes Thema im Forum.<br>
Die Variante mit einer eigenen Seite entspricht der klassischen, vollständig automatisierten Lösung: Nutzer:innen und Gäste können ihre Reservierungen direkt über ein Formular eintragen, ohne dass ein Eingreifen des Teams notwendig ist. Es wird kein Benachrichtigung geben über einen neuen Eintrag.<br>
Alternativ kann ein Reservierungsthema im Forum verwendet werden. Diese Variante orientiert sich an der old school Variante, bei der sich Interessierte per Beitrag im Thema melden müssen. Teammitglieder tragen die Reservierungen anschließend über ein Formular ein. Die weitere Verwaltung, bleibt dennoch weiterhin automatisch.<br>
Eingeloggte Mitglieder können ihre eigenen Reservierungen jederzeit verlängern - sofern es den Bedingungen der jeweiligen Kategorie erlauben und das festgelegte Maximum noch nicht erreicht wurde - und löschen. Die Zuordnung der Reservierungen erfolgt dabei automatisch über den Accountswitcher. Teammitglieder können entsprechend der Bedingungen, diese Funktionen bei allen Reservierungen nutzen.<br>
<br>
Neben den aktiven Reservierungen unterstützt das Plugin zusätzlich gesperrte Reservierungen. Diese entstehen, wenn nach Ablauf oder der Löschung einer Reservierung eine zeitlich begrenzte Sperre greift und der gleiche Eintrag nicht sofort erneut reserviert werden darf von dieser/m User:in. Die Übersicht über solche gesperrten Reservierungen ist ausschließlich für Teammitglieder sichtbar.<br>
In den Plugin-Einstellungen kann festgelegt werden, ob diese gesperrten Reservierungen direkt auf der extra Seite/Showthread angezeigt werden sollen oder ob sie in einer separaten Übersicht im Moderationsbereich (ModCP) erscheinen. Unabhängig davon haben Teammitglieder die Möglichkeit, gesperrte Reservierungen vorzeitig zu entfernen - allerdings nur bei Einträgen anderer Nutzer:innen, nicht bei ihren eigenen.<br>
<br>
Im ACP steht jederzeit eine vollständige Übersicht aller Reservierungen zur Verfügung - einschließlich gesperrter Reservierungen. Dort können Reservierungen bei Bedarf bearbeitet werden, beispielsweise wenn eine falsche Kategorie gewählt wurde oder ein Tippfehler korrigiert werden muss. Außerdem besteht die Möglichkeit, die zugehörige UID anzupassen.<br>
Existiert eine UID im Forum nicht mehr - nach Accountlöschung - obwohl noch mindestens eine Reservierung mit ihr verknüpft ist, wird dem Team auf dem Index ein entsprechender Hinweisbanner angezeigt. So wird direkt sichtbar, dass eine UID entsprechend angepasst (andere UID von dem/der User:in) oder die betroffene Reservierung gelöscht werden sollte.<br>
<br>
Zusätzlich kann ein Banner für auslaufende Reservierungen angezeigt werden. Dieser wird allen Nutzer:innen angezeigt und weist darauf hin, dass eine Reservierung in Kürze abläuft. Wie viele Tage vor dem Ablauf dieser Hinweis erscheinen soll, kann in den Plugin-Einstellungen festgelegt werden. Jede einzelne Reservierung kann über diesen Banner genau einmal ausgeblendet werden.<br>
<br>
Darüber hinaus gibt es eine globale Übersicht für eingeloggte Mitglieder, in der alle eigenen Reservierungen angezeigt werden. Diese Übersicht umfasst sowohl aktive als auch gesperrte Einträge. Die Variable ist global einsetzbar - empfohlen wird das UserCP. Über diese Übersicht ist keine Verwaltung möglich.

# Funktionen im Überblick
- Flexibles, kategoriebasiertes Reservierungssystem mit beliebig vielen unabhängigen Kategorien
- Individuelle Reservierungsregeln pro Nutzergruppe (Anzahl, Laufzeit, Verlängerungen, Sperrfristen)
- Regelprüfung über primäre oder sekundäre Nutzergruppen
- Automatische Prüfung auf bereits vergebene Einträge über Profilfelder oder vorhandene Nutzernamen
- Flexible Darstellung der Reservierungen als Liste oder gruppiert nach Nutzergruppen
- Reservierungen über eigene Seite oder über ein Forumsthema verwalten
- Eigenständige Verwaltung der eigenen Reservierungen (löschen und verlängern, wenn erlaubt)
- Teamübersicht für gesperrte Reservierungen mit Möglichkeit zur vorzeitigen Entfernung
- Umfassende Verwaltung im ACP inklusive Bearbeitung und Anpassung von Reservierungen
- Hinweisbanner für auslaufende Reservierungen und fehlende Account-Zuordnungen
- Globale Übersicht aller eigenen Reservierungen für eingeloggte Mitglieder

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Datenbank-Änderungen
hinzugefügte Tabelle:
- reservations
- reservations_grouppermissions
- reservations_types

# Neue Sprachdateien
- deutsch_du/admin/reservations.lang.php
- deutsch_du/reservations.lang.php

# Einstellungen<br>
- Reservierungssystem
- Reservierungsthema
- Teamgruppen
- Gruppenberechtigung
- Spitzname
- Geschlechtsoptionen
- Infotext
- Reservierungen für Gesuche
- Tabs
- Anzeige gesperrter Reservierungen
- Index Anzeige
- Anzeige eigener Reservierungen
- Listen PHP
- Listen Menü
- Listen Menü Template<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.

# Neue Template-Gruppe innerhalb der Design-Templates
- Reservierungs-Manager

# Neue Templates (nicht global!)
- reservations_banner_reminder
- reservations_banner_team
- reservations_blocked_page
- reservations_blocked_reservations
- reservations_blocked_showthread
- reservations_blocked_types
- reservations_modcp
- reservations_modcp_nav
- reservations_output_entry
- reservations_output_tabs
- reservations_output_tabs_content
- reservations_output_tabs_menu
- reservations_output_types
- reservations_output_types_gender
- reservations_output_types_groups
- reservations_own
- reservations_own_reservations
- reservations_own_types
- reservations_page
- reservations_page_formular
- reservations_showthread
- reservations_showthread_formular<br>
<br>
<b>HINWEIS:</b><br>
Alle Templates wurden größtenteils ohne Tabellen-Struktur gecodet. Das Layout wurde auf ein MyBB Default Design angepasst.

# Neue Variablen

# Neues CSS - inplayqscenes.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default. Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.
```css
.reservations-desc {
	text-align: justify;
	padding: 20px 40px;
}

.reservations_formularPage form {
	width: 30%;
	margin: 10px auto;
}

.reservations_formularShowthread form {
	width: 84%;
	margin: 10px auto;
}

.reservations_formular-label {
	font-weight: bold;
	width: 100%;
}

.reservations_formular-input {
	margin-bottom: 8px;
	display: flex;
	flex-wrap: nowrap;
	gap: 10px;
	justify-content: space-between;
}

.reservations_formular-select, 
.reservations_formular-select select,
.reservations_formular-field,
.reservations_formular-field input.textbox,
.reservations_formular-field .select2-container {
	width: 100%;
	box-sizing: border-box;
}

.reservationsSingle, 
.reservations-genderflex {
	display: flex;
	flex-wrap: nowrap;
	justify-content: flex-start;
}

.reservations-reservation {
	width: 100%;
}

.reservations-genderline {
	font-weight: bold;
	padding: 3px;
}

.reservations_entry {
	padding: 5px;
}

.reservations_types {
	margin-bottom: 10px;
}

/* SHOWTHREAD */
.reservations_showthread {
	display: flex;
	flex-wrap: nowrap;
	gap: 10px;
	align-items: flex-start;
}

.reservations_showthread-guide {
	width: 40%;
}

.reservations_showthread-output {
	width: 60%;
}

.reservations_showthread-desc {
	text-align: justify;
	padding: 20px 40px;
}

/* TABS */
.reservationTab {
	display: flex;
	flex-wrap: nowrap;
}

.reservationTablinks {
	padding: 10px;
	transition: 0.3s;
	cursor: pointer;
}

.reservationTablinks:hover {
	background-color: #ddd;
}

.reservationTablinks.active {
	background-color: #ccc;
	font-weight: bold;
}

.reservationTabcontent {
	display: none;
}

.reservations_ownreservations {
	margin-bottom: 20px;
}

.reservations_ownreservations-types,
.reservations_blockedreservations-types {
	display: flex;
}

.reservations_ownreservationsBit,
.reservations_blockedreservationsBit{
	width: 100%;
}

.reservations_ownreservations-title,
.reservations_blockedreservations-title{
	font-weight: bold;
	padding: 3px;
}

.reservationsBanner {
	font-size: 14px;
	margin-top: -2px;
	float: right;
}
```

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Reservierungstypen und Reservierungen haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für die Reservierungen befinden sich im Tab 'RPG Erweiterungen'.

# Links
### ACP
<b>Reservierungstypen</b><br>
index.php?module=rpgstuff-reservations_types<br>
<br>
<b>alle Reservierung</b><br>
index.php?module=rpgstuff-reservations_data&action=X<br>
(individuell nach Typen)<br>

### Forum
<b>Eigene Seite</b><br>
misc.php?action=reservations<br>
<br>
<b>ModCP</b><br>
modcp.php?action=reservations

# Demo
## ACP
### Reservierungstypen
<img src="https://stormborn.at/plugins/reservations_acp_type.png">
<img src="https://stormborn.at/plugins/reservations_acp_type_add.png">
<img src="https://stormborn.at/plugins/reservations_acp_grouppermission_add.png">

### alle Reservierungen
<img src="https://stormborn.at/plugins/reservation_acp_reservations.png">
<img src="https://stormborn.at/plugins/reservation_acp_reservations_edit.png">

## Forum
### eigene Seite
<img src="https://stormborn.at/plugins/reservation_misc.png">
<img src="https://stormborn.at/plugins/reservation_misc_tabs.png">
<img src="https://stormborn.at/plugins/reservation_misc_form_guest.png">
<img src="https://stormborn.at/plugins/reservation_misc_form_wanted.png">

### Showthread
<img src="https://stormborn.at/plugins/reservation_thread.png">
<img src="https://stormborn.at/plugins/reservation_thread_tabs.png">

### Error Anzeige
<img src="https://stormborn.at/plugins/reservations_form_error.png">

### gesperrte Reservierungen
<img src="https://stormborn.at/plugins/reservation_blocked.png">

### ModCP
<img src="https://stormborn.at/plugins/reservation_blocked_modcp.png">

### Banner
<img src="https://stormborn.at/plugins/reservation_banner_reminder.png">
<img src="https://stormborn.at/plugins/reservation_banner_oldUID.png">

### eigene Reservierungen
<img src="https://stormborn.at/plugins/reservation_own.png">
