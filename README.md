# User Geburtstagsgrüße
Mit diesem kleinen Plugin erhalten User:innen an ihrem Geburtstag automatisch einen Geburtstagsgruß im Forum. Optional kann auch das Team benachrichtigt werden, wenn ein Mitglied Geburtstag hat.<br>
<br>
Die Geburtstage können flexibel über ein Profilfeld, ein Steckbrieffeld (aus dem Plugin <a href="https://github.com/katjalennartz/application_ucp" target="_blank">Steckbriefe im UCP</a> von risuena) oder das MyBB-eigene Geburtstagsfeld erfasst werden. Bei Profil- und Steckbrieffeldern (vom Typ Text) muss das Datum im Format TT.MM.YYYY eingetragen werden – das Jahr ist optional, allerdings muss der Punkt nach dem Monat vorhanden sein (z.B. 23.05.).<br>
<br>
Zusätzlich bietet das Plugin eine übersichtliche Liste aller eingetragenen Geburtstage. So behalten Team und Community jederzeit den Überblick.

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Neue Sprachdateien
- deutsch_du/userbirthdays.lang.php

# Einstellungen
- Geburtstagsoption
- Geburtstagsfeld
- Spitzname
- Geburtstagstext
- Teambenachrichtigung
- Liste aller Geburtstage
- Erlaubte Gruppen
- Listen PHP
- Listen Menü
- Listen Menü Template
<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.

# Neue Templates
- userbirthdays_index
- userbirthdays_list
- userbirthdays_list_month
- userbirthdays_list_user
- userbirthdays_teambanner

# Neue Variablen
- header: {$index_userbirthdays}{$team_userbirthdays}

# Links
FORENLINK/misc.php?action=userbirthdays

# Demo 
Geburtstags-Gruß
<img src="https://stormborn.at/plugins/birthday_index.png" />

Team Benachrichtigung
<img src="https://stormborn.at/plugins/birthday_team.png" />

Geburtstagsliste
<img src="https://stormborn.at/plugins/birthday_list.png" />
