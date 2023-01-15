# NSPanelConfig

Dieses Modul bindet das Sonoff NSP-Panel (EU / US) mit der lovelace UI in Symcon ein.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* flexible Darstellung beliebiger Menüebenen in beliebiger Schachtelung auf dem Display
* Bildschirmschoner mit Uhrzeit / Datumsanzeige
* frei konfigurierbare Variableneinbindung aus Symcon, um Inhalte von Variablen auf dem Display darzustellen
* anlegen von Aktionen, um RequestActions / Scripte / Scripte mit Parametern aufzurufen

Aktuell werden der Temp. Sensor, die Tasten sowie die Relais weder abgefragt noch angesteuert.

### 2. Voraussetzungen

- IP-Symcon ab Version 6.0

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen https://github.com/boni127/NSPanel-by-boni.git

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'NSPanelConfig'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Bedienung__:

Die Ansteuerung des Displays erfolgt über die nativen Befehle für das NSPanel, die auf https://docs.nspanel.pky.eu und https://github.com/jobr99/nspanel-lovelace-ui dokumentiert sind.

Die Instanzkonfiguration gliedert sich in den Konfigurations- und Aktions-Bereich, via MQTT Topic wird die ID des zu steuernden NSPanels konfiguriert.
Der Bereich Screensaver ist weitestgehend selbsterklärend und wird mit Werten vorbelegt.

Die drei Tabellen legen die 

* Seitendefinition
* Wertzuweisungen
* Aktionszuweisungen 

an.

#### Seitendefinitionen

![Seitendefinitionen](./seitendefinitionen.png)

* **ID** (1) definiert die ID der darzustellenden Seite, die Zahl darf zwischen 0 und  9999 liegen, um Konflikte mit Object-ID's in Symcon zu vermeiden
* **Ebene** (2) legt die Ebene der Seite fest, ein leerer Eintrag stellt die Hauptebene da.  Wertebereich 1-9999. Dieser Eintrag wird auch zum Rücksprung zur 
übergeordneten Ebene genutzt, es wird zu der Seite und Ebene gesprungen, die hier angegeben wird
* **zurück zu** (3) definiert die Rücksprungebene, ist hier nichts definiert wird zur ersten Seite der Hauptebene zurückgesprungen.
* **Typ** (4) legt den Seitentyp fest, cardEntities, cardGrid, cardMedia, ....
* **Eintrag** (5) legt den Inhalt der Seite fest, lovelave ui Konfigurationsstring

Ein Beispiel:

ID   | Ebene  | zurück | Typ | Eintrag
---- | ------ | -------| --- | -----
1    |        |        |pageType~cardEntities|entityUpd~Leuchten\~1\|1\~light\~30691\~\~17299\~Hue Lampe\~0\~...
2    |        |        |pageType~cardMedia   |entityUpd~Bad\~2\|\~41321\~\~17690\~Title-line\~17690\~author-line...
10   |        |        |pageType~cardGrid    |entityUpd~Radio\~1\|1\~light\~1101\~\~17299\~Fav1\~1\~....
1104 | 1      |        |pageType~cardGrid    |entityUpd~Szenen\~2\|1\~light\~1102\~\~17299\~Szene 1\~1\~...
1105 | 1      |        |pageType~cardGrid    |entityUpd~Szenen\~1\|0\~light\~1108\~\~17299\~Szene 6\~1\~light\~1109\~\~17299\~Szene 7\~1

Die erste dargestellte Seite hat die ID **1**, von hier ausgehend wechseln die Navigationselemente am oberen Rand des Displays zu den Seiten **2**, **10**, und wieder auf die **1**.
Seite **1** enthält einen Sprung auf die  Seite **1104**. Gekennzeichnet durch den Eintrag in der Spalte *Ebene* wird hier zwischen den Seite **1104** und **1105** gewechselt. Der
Rücksprung in die übergeordnete Ebene erfolgt ebenfalls über den Eintrag *Ebene* zur Seite **1**


Hier beispielhaft der Eintrag für die erste Seite. 

`entityUpd~Leuchten~1|1~light~30691~~17299~Hue Lampe~0~number~10007~~17299~Dimmer~0|0|255~light~50151~~17200~Wandlampe~1~input_sel~1104~~17299~Szenensteuerung~aufrufen`

`entityUpd~Leuchten~1|1` erzeugt die Überschrift *Leuchten* und die Navigationspfeile links / rechts
`light~30691~~17299~Hue Lampe~0` ist das erste Element auf der Seite, eine Checkbox mit der Beschriftung *Hue Lampe*.

Wichtig ist hier der zweite Eintrag in der durch \~ Zeichen getrennten Liste. Hier steht der interne Name des Elements auf der Seite: 30691.
An dieser Stelle erwartet das Module entweder eine Objekt-ID eines Symcom Objektes ( 10000-60000 ) oder die ID einer Seiten, die aufgerufen werden soll.

Hier dazu das zweite Beispiel:

`input_sel~1104~~17299~Szenensteuerung~aufrufen` Dies erzeugt das Element *Szenensteuerung* mit einem Button *aufrufen* auf dem Panel. Über den Button erfolgt der Aufruf der ID 1104.

Das war es auch schon, nun kann in der Seite navigiert werden und über die Checkbox *Hue Lampe* via RequestAction(30691,\<Value\>) . ( \<Value\> ist beim Element *light* 0 oder 1) das erste Object geschaltet werden.
Infos zur Syntax des lovelace ui ist hier https://docs.nspanel.pky.eu und https://github.com/jobr99/nspanel-lovelace-ui zu finden.

##### popupNotify

Beim direkten Aufruf der Infoseite pageType~popupNotify aus dem Menü ergibt sich folgende Besonderheit: Die Seite popupNotofy hat am oberen Bildschirmrand keine Navigationstasten, sondern nur ein Exit. Somit muß in der Seitendefinition eine Rücksprungadresse entweder über *Ebene* oder *zurück* angegeben werden. Fehlen diese Angeben erfolgt der Rücksprung zur ersten Menuseite.

Beispiel:

ID   | Ebene  | zurück | Typ | Eintrag
---- | ------ | -------| --- | -----
1    |        |        |pageType~cardEntities|entityUpd~Leuchten\~1\|1\~light\~30691\~\~17299\~Hue Lampe\~0\~...
2    |        |        |pageType~cardMedia   |entityUpd~Bad\~2\|\~41321\~\~17690\~Title-line\~17690\~author-line...
10   |        |        |pageType~cardGrid    |entityUpd~Radio\~1\|1\~light\~1101\~\~17299\~Fav1\~1\~....
1104 | 1      |        |pageType~cardGrid    |entityUpd~Szenen\~2\|1\~light\~1102\~\~17299\~Szene 1\~1\~...
1105 | 1      |        |pageType~cardGrid    |entityUpd~Szenen\~1\|0\~light\~1108\~\~17299\~Szene 6\~1\~light\~1109\~\~17299\~Szene 7\~1
3    |        | 2      |pageType~popupNotify |entityUpdateDetail\~9999\~Hinweis\~23456\~Ja\~17289\~Nein\~34131\~Info\~17299\~0

Wird hier nun Seite **3** aufgerufen, führt das Verlassen von Seite **3** direkt zu Seite **2**.


#### Wertzuweisung

Über die Tabelle Wertzuweisung werden die Verknüpfungen der Symcon Objekte mit den einzelnen Seiten auf den Panel definiert.

* **Variable** : Objekt-ID der IPS Variable, die auf dem Display dargestellt werden soll
* **Trenner** : das Lovelace UI Element numbers erwartet einen Wertebereich (akt. Wert\|Min\|Max) durch \| getrennt. Hier kann der Trenner festgelegt werden. Bislang habe ich aber nur das Pipe-Symbol als Trenner gefunden
* **formatiert** : fragt die im IPS Variablenprofil hinterlegte Einheit mit ab
* **Ergebnisspalte** : Ziel für den Wert im Konfigurationsstring des Displays

##### Beispiel:

Im IPS ist ein Dimmer und ein Switch angelegt, die Helligkeit des Dimmers ist in der IPS Variable 10007, der Status des Switch in 30691 definiert.

Mit dieser Seiten Definition wird ein Switch und ein Slider auf dem NSPanel angezeigt

ID   | Ebene  | zurück | Typ | Eintrag
---- | ------ | -------| --- | -----
1    |        |        |pageType~cardEntities|entityUpd~Leuchten\~1\|1\~light\~30691\~\~17299\~Hue Lampe\~0\~number\~10007\~\~17299\~Dimmer\~0\|0\|255

Der Eintrag ist durch das \~ Symbol in 15 Spalten getrennt, Spalte 0 - 14

In der Tabelle Wertzuweisung werden nun folgende Zuordnungen für die Seite **1** festgelegt

Variable | Trenner | formatiert | Ergebnisspalte
-------- | ------- | ---------- | --------------
30691    |         |            | 8
10007    | \|      |            | 14

Damit kann nun vom Display der Switch 30691 und der Dimmer 10007 bedient werden, ändern sich die IPS-Variablne, wird der Displayinhalt aktualisiert. Die Interaktion mit IPS erfolgt über RequestAktion

### Aktionszuweisung

Werden andere Interaktionen mit IPS benötigt können diese in der Tabelle Aktionszuweisungen angelegt werden.
Interaktionen mit dem Display senden einen Antwortstring an IPS. Ist der Switch *debug* aktiviert, werden diese ins Logfile geschrieben.

* **Seite/ Objekt** : ID des Objektes oder der aufgerufenen Seite
* **result** : Antwortstring des Elements
* **Aktion** : Auswahl der zu startenden Aktion: RequestAktion, RunScript, RunScriptEx, Aufruf einer Displayseite
* **toggle** : Abfrage einer boolschen Variable und Wechsel des Wertes, um beispielsweise über einen Button auf einer cardGrid eine Lampe ein- und auszuschalten.
Wird **toggle** gesetzt, erfolgt für die nachfolgenden Felder **maxstep** und **value** keine Auswertung mehr
* **maxstep** : begrenzt den Wert auf eine  mit **maxstep** definierte Schrittweite. Beispiel Lautstärkeregler. Aktuelle Lautstärke 7, **maxstep** ist auf 5 eingestellt,
der Slider für die Lautstärke auf dem Display wird auf 80 eingestellt, über **maxstep** wird die Lautstärke jedoch auf 12 (7+5) begrenzt. Wird **maxstep** gesetzt, erfolgt für das nachfolgende Feld **value** keine Auswertung mehr
* **value** : Wert, der an IPS übermittelt werden soll, wenn dieser von dem im Antwortstring empfangenden Wert abweichen soll


Beispiel:

Ein Button in einem cardGrid gibt beim Betätigen nur die Information über die Betätigung des Buttons zurück:

`event,buttonPress2,1101,button`

Die dritte Spalte gibt die ID des Objektes an, Spalte 4 das Ergebnis. Um nun auf den oberen Antwortstring mit dem Aufruf, bspw. eines Scripts, zu reagieren legt man in der Tabelle *Aktionszuweisung* folgenden Eintrag an:

Seite | 1101

result | filter | Aktion       | Seite/ Objekt | toggle | maxstep | value
------ | ------ | ------------ | ------------- | ------ | ------- | -------
button |        | start script | 34718         |        |         |

34718 ist die Objekt-ID eines Scripts

Nun wird mit der Betätigung des Buttons das Script aufgerufen

Beispiel:

Steuerung eines Rollos mit Shelly, über die Variable *Rollo* des Moduls von Kai Schnittcher kann das Rollo die Aktionen Stop (2), Open (0), Close (4) ausführen. 

Bei der Betätigung des elemets *shutter* auf dem NSPanel liefert das Panel folgende Informationen an das Modul

`event,buttonPress2,18163,up`

`event,buttonPress2,18163,stop`

`event,buttonPress2,18163,down` 


Um das Rollo zu steuern muß diese Definition vorliegen

Seite | 18163

result | filter | Aktion        | Seite/ Objekt | toggle | maxstep | value
------ | ------ | ------------- | ------------- | ------ | ------- | -------
up     |        | RequestAction | 18163         |        |         | 0
stop   |        | RequestAction | 18163         |        |         | 2
down   |        | RequestAction | 18163         |        |         | 4

18163 ist die ID der Variable *Rollo* der Instanz *Shelly2*

### Hilfreiches

Im Aktion-Bereich gibt es einen Listhelper. Hier können die Spalten der einzelnen Seite dargestellt werden, um einfacher die entsprechende Spalte im lovelace ui Konfig-String zu finden

Über die beiden Buttons Send lassen sich zum Testen Konfig-Strings an das Display senden, über Save und Load kann die aktuelle Konfiguration gespeichert und wieder geladen werden.

### 5. Statusvariablen und Profile


#### Statusvariablen

Aktuell keine

#### Profile

Aktuell keine

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

Aktuell keine.


### 7. PHP-Befehlsreferenz

Aktuell keine.
