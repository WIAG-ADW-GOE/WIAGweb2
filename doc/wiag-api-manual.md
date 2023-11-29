# WIAG API

Das Application Programming Interface (API) für WIAG ermöglicht die automatisierte
Abfrage von Daten aus dem WIAG Datenbestand:

Die Daten werden in einem der folgenden Formate ausgeliefert: CSV, [JSON](https://www.json.org/json-de.html), [JSON-LD](https://json-ld.org/), [RDF-XML](https://www.w3.org/TR/rdf-syntax-grammar/)

## Bischöfe

### Einzelabfrage
Mit der Angabe einer WIAG-Kennung erhält man alle Elemente eines Datensatzes.
Die URL hat folgenden Aufbau: `https://wiag-vocab.adw-goe.de/id/[ID]?format=[Json|Csv|Rdf|Jsonld]`.

Beispiele:  
<https://wiag-vocab.adw-goe.de/id/WIAG-Pers-EPISCGatz-03848-001?format=Json>  
<https://wiag-vocab.adw-goe.de/id/WIAG-Pers-EPISCGatz-03848-001?format=Rdf>  

#### Struktur
Das JSON-Dokument enthält Angaben zu der jeweiligen Person in der Form von Paaren mit der Struktur `"key":"value"`.
Bei fast allen Personen gibt es auch eine Gruppe von externen Kennungen im Element `identifiers` sowie eine Liste von Ämtern im Element `offices`.

Beispiel:
``` json
 {"wiagId":"WIAG-Pers-EPISCGatz-10076-001",
  "familyName":"Braida",
  "givenName":"Franz Julian",
  "prefix":"Graf von",
  "comment_person":"Ep. tit. Hipponensis",
  "dateOfBirth":"1654",
  "dateOfDeath":"1727",
  "identifier":
  {"viafId":"5652149719115111130002",
   "wikidataId":"Q12017135"},
  "offices":[
	  {"officeTitle":"Weihbischof",
	   "diocese":"ecclesia Olomucensis",
	   "dateStart":"1703",
	   "dateEnd":"1727"},
	  {"officeTitle":"Generalvikar",
	   "diocese":"ecclesia Olomucensis",
	   "dateStart":"1703",
	   "dateEnd":"1727"}],
  "reference":
  {"title":"Die Bischöfe des Heiligen Römischen Reiches 1648 bis 1803",
   "author":"Gatz, Erwin",
   "short":"Gatz, Bischöfe 1648 bis 1803",
   "pages":"41"
  }
 }
```

CSV: Die erste Zeile enthält die Feldbezeichner. Die
folgende Zeile enthält die Feldwerte. Die Feldinhalte einer Zeile sind durch
Tabulator voneinander getrennt.

Beispiel:
``` text
wiagId	familyName	givenName	prefix	commentPerson	dateOfBirth	dateOfDeath	identifier.viafId ...
WIAG-Pers-EPISCGatz-10076-001	Braida	"Franz Julian"	"Graf von"	"Ep. tit. Hipponensis"	1654	1727	5652149719115111130002 ...

```

<a id="csvinbrowser"></a>Die meisten Browser zeigen einen Auswahldialog, bei dem entschieden werden kann, ob
die Daten in einer Datei gespeichert oder direkt angezeigt werden sollen. Hinweis für
Microsoft-Windows Benutzer: Die Anwendung *Editor* zeigt die Daten korrekt an. Die
Anwendung *Excel* geht eventuell von einer anderen Kodierung als UTF-8 aus und
erwartet ein Komma statt des Tabulators als Trennzeichen. Daher ist die Anzeige
direkt in *Excel* meistens nicht sinnvoll. Die Daten können aber über den Importdialog korrekt eingelesen werden,
indem die Auswahl für
*Dateiursprung* und *Trennzeichen* entsprechend eingestellt werden.

### Suchanfrage
Mit der Angabe von Suchparametern erhält man alle Datensätze, die der Suchanfrage
entsprechen. Gesucht werden kann nach folgenden Eigenschaften:

- **name**: Finde Übereinstimmugen in Vorname, Nachname, Namenspräfix, Varianten des
  Vornames und Varianten des Nachnamens.
  Beispiele: `Josef`, `Graf`,
  `gondo`, `Franz Josef Graf von Gondola`
- **diocese**: Finde Übereinstimmugen in den Namen der Bistümer, in denen die Person ein
  Amt innehatte.
  Beispiele: `basel`, `würzburg`.
- **office**: Finde Übereinstimmungen in den Amtsbezeichnungen.
  Beispiele: `vikar`,
  `administrator`.
- **year**: Finde Übereinstimmungen für einen Zeitraum von plus/minus einem Jahr zu der
  angegebenen Jahreszahl. Berücksichtigt wird für die einzelne Person der größte
  Zeitraum, der sich ergibt aus Geburtsdatum, Sterbedaten, Amtsbeginn und Amtsende.
- **someid**: Finde eine exakte Übereinstimmungen mit einer Kennung für eine Person in
  folgenden Verzeichnissen:
  - [WIAG](https:/wiag-vocab.adw-goe.de)
  - [Gemeinsame Normdatei (GND)](https://explore.gnd.network)
  - [Virtual International Authority File (VIAF)](https://viaf.org/)
  - [Wikidata](https://www.wikidata.org)
  - [Personendatenbank der Germania Sacra](http://personendatenbank.germania-sacra.de/)

Die Suchparameter sind logisch UND-verknüpft: Es werden nur solche Datensätze angezeigt, für die alle Parameter/Wert-Kombinationen zutreffen.
Die Suchparameter werden an die URL jeweils mit dem Schlüsselwort angehängt. Ebenso
wird das gewünschte Format mit dem Schlüsselwort `format` angehängt. JSON ist das
Standard-Format, d.h. hier kann die Angabe des Formats entfallen:

`http://wiag-vocab.adw-goe.de/data/epc?key1=value1&key2=value2&format=[Json|Csv|Rdf|Jsonld]`

Beispiele:  
<https://wiag-vocab.adw-goe.de/data/epc?name=Gondo>  
<https://wiag-vocab.adw-goe.de/data/epc?name=Hohenlohe&diocese=Bamberg&format=Json>  
<https://wiag-vocab.adw-goe.de/data/epc?diocese=Trier&format=Rdf>  
<https://wiag-vocab.adw-goe.de/data/epc?someid=WIAG-Pers-EPISCGatz-10191-001&format=Rdf>  
<https://wiag-vocab.adw-goe.de/data/epc?someid=Q15435829&format=Csv>  


#### Struktur
Das JSON-Dokument enthält ein Element `persons`, mit der Liste der Datensätze.

Beispiel:
```json
{
  "persons": [
    {
      "wiagId": "WIAG-Pers-EPISCGatz-03302-001",
      "familyName": "Hohenlohe",
      "givenName": "Georg",
      "prefix": "von",
      "dateOfBirth": "um 1350",
      "dateOfDeath": "1423",
      "identifier": {
        "gsId": "019-01009-001",
        "gndId": "124115535",
        "viafId": "15696513",
        "wikidataId": "Q1506604",
        "wikipediaUrl": "https://de.wikipedia.org/wiki/Georg_von_Hohenlohe"
      },
      "offices": [
        {
          "officeTitle": "Bischof",
          "diocese": "Passau",
          "dateStart": "1389",
          "dateEnd": "1423",
          "sort": 6000
        },
		...
      ]
    },
	{
      "wiagId": "WIAG-Pers-EPISCGatz-02554-001",
      "familyName": "Hohenlohe",
      "givenName": "Friedrich",
      "prefix": "von",
      "dateOfDeath": "1352",
      "identifier": {
        "gsId": "054-00923-001",
        "gndId": "110092236",
        "viafId": "37502849",
        "wikidataId": "Q1459890",
        "wikipediaUrl": "https://de.wikipedia.org/wiki/Friedrich_I._von_Hohenlohe"
      },
	  ...
    }
	...
  ]
}
```

Das CSV Dokument enthält in der ersten Zeile die Feldbezeichner. Die
folgenden Zeilen enthalten die Feldwerte. Die Feldinhalte einer Zeile sind durch Tabulator voneinander getrennt. Die Zeichencodierung ist UTF-8.

Beispiel:
```text
wiagId	familyName	givenName	prefix	commentPerson	dateOfBirth	dateOfDeath	identifier.gsId	identifier.gndId	...
WIAG-Pers-EPISCGatz-03302-001	Hohenlohe	Georg	von		"um 1350"	1423	019-01009-001	124115535	...
WIAG-Pers-EPISCGatz-02554-001	Hohenlohe	Friedrich	von			1352	054-00923-001	110092236	...
WIAG-Pers-EPISCGatz-12609-001	Hohenlohe-Waldenburg-Bartenstein	"Joseph Christian Franz"	"Prinz zu"		1740	1817	048-03097-001	119536463	...
WIAG-Pers-EPISCGatz-03753-001	Hohenlohe	Gottfried	von			1322	059-00674-001	100943365	...
WIAG-Pers-EPISCGatz-03757-001	Hohenlohe	Albrecht	von			1372	059-00048-001	11864775X	...
WIAG-Pers-EPISCGatz-12605-001	Hohenlohe-Waldenburg-Schillingsfürst	"Franz Karl Joseph"	"Fürst von"	"1812–1819 Generalvikar von Ellwangen. Titularbistum Tempe"	1745	1819		1169559   ...
```

Siehe [Hinweise zur Anzeige im Browser](#csvinbrowser).

## Domherren

Die Art der Abfrage und die Struktur der Antwort entspricht derjenigen der Bischöfe.

### Einzelabfrage
Mit der Angabe einer WIAG-Kennung erhält man alle Elemente eines Datensatzes.
Die URL hat folgenden Aufbau: `https://wiag-vocab.adw-goe.de/id/[ID]?format=[Json|Csv|Rdf|Jsonld]`.

Beispiele:  
<https://wiag-vocab.adw-goe.de/id/WIAG-Pers-CANON-43103-001?format=Json>  
<https://wiag-vocab.adw-goe.de/id/WIAG-Pers-CANON-43103-001?format=Rdf>  

### Suchanfrage
Mit der Angabe von Suchparametern erhält man alle Datensätze, die der Suchanfrage
entsprechen. Gesucht werden kann nach folgenden Eigenschaften:

- **name**: Finde Übereinstimmugen in Vorname, Nachname, Namenspräfix, Varianten des
  Vornames und Varianten des Nachnamens.
  Beispiele: `Josef`, `Marquard`, `Brand`
- **domstift**: Finde Übereinstimmugen in den Namen der Domstifte oder des Klosters, 
  in denen die Person ein Amt innehatte.
  Beispiele: `Verden`.
- **office**: Finde Übereinstimmungen in den Amtsbezeichnungen.
  Beispiele: `Domherr`, `Scholaster`.
- **place**: Finde Übereinstimmungen in den Orten, wo die Person ein Amt innehatte.
  Beispiele: `Zurzach`.
- **year**: Finde Übereinstimmungen für einen Zeitraum von plus/minus einem Jahr zu der
  angegebenen Jahreszahl. Berücksichtigt wird für die einzelne Person der größte
  Zeitraum, der sich ergibt aus Geburtsdatum, Sterbedaten, Amtsbeginn und Amtsende.
- **someid**: Finde eine Übereinstimmungen mit einer Kennung für eine Person in
  folgenden Verzeichnissen:
  - [WIAG](https:/wiag-vocab.adw-goe.de)
  - [Gemeinsame Normdatei (GND)](https://explore.gnd.network)
  - [Virtual International Authority File (VIAF)](https://viaf.org/)
  - [Wikidata](https://www.wikidata.org)
  - [Personendatenbank der Germania Sacra](http://personendatenbank.germania-sacra.de/)

Die Suchparameter sind logisch UND-verknüpft: Es werden nur solche Datensätze angezeigt, für die alle Parameter/Wert-Kombinationen zutreffen.
Die Suchparameter werden an die URL jeweils mit dem Schlüsselwort angehängt. Ebenso
wird das gewünschte Format mit dem Schlüsselwort `format` angehängt. JSON ist das
Standard-Format, d.h. hier kann die Angabe des Formats entfallen:

`http://wiag-vocab.adw-goe.de/data/can?key1=value1&key2=value2&format=[Json|Csv|Rdf|Jsonld]`

Beispiele:  
<https://wiag-vocab.adw-goe.de/data/can?name=Marquard>  
<https://wiag-vocab.adw-goe.de/data/can?name=Brand&domstift=Verden&format=Json>  
<https://wiag-vocab.adw-goe.de/data/can?domstift=Trier&format=Rdf>  
<https://wiag-vocab.adw-goe.de/data/can?someid=WIAG-Pers-Canon-43103-001&format=Rdf>  
<https://wiag-vocab.adw-goe.de/data/can?someid= Q94961412&format=Csv>  

## Bistümer

### Einzelabfrage
Mit der Angabe einer WIAG-Kennung erhält man alle Elemente eines Datensatzes. 
Die URL hat folgenden Aufbau: `https://wiag-vocab.adw-goe.de/id/[ID]?format=[Json|Csv|Rdf|Jsonld]`

Beispiele:  
<https://wiag-vocab.adw-goe.de/id/WIAG-Inst-DIOCGatz-047-001?format=Jsonld>  
<https://wiag-vocab.adw-goe.de/id/WIAG-Inst-DIOCGatz-047-001?format=Csv>

#### Struktur
Das JSON-Dokument enthält ein Element `diocese`, das die einzelnen Angaben zu dem
Bistum umfasst. Dazu gehört bei fast allen Bistümern eine Gruppe von externen Kennungen im Element `identifiers` sowie eine Liste von alternativen Bezeichnungen in unterschiedlichen Sprachen im Element `altLabels`.

Beispiel:
``` json
{
  "diocese": {
    "wiagid": "WIAG-Dioc-47-001",
    "name": "Basel",
    "status": "Bistum",
    "dateOfFounding": "4. Jahrhundert",
    "dateOfDissolution": "1803",
    "altLabels": [
      {
        "altName": {
          "name": "ecclesia Basileensis",
          "lang": "la"
        }
      },
      {
        "altName": {
          "name": "Bâle",
          "lang": "fr"
        }
      },
      {
        "altName": {
          "name": "Basilea"
        }
      }
    ],
    "note": "Erste Erwähnungen eines Bischofs in Kaiseraugst bei Basel gehen auf 343/346 zurück. Die Kontinuität zum späteren Bistum Basel bleibt jedoch offen. Zur eigentlichen Christianisierung kam es erst im 7. Jahrhundert",
    "ecclesiasticalProvince": "Besançon",
    "bishopricSeat": "Basel",
    "noteBishopricSeat": "Im Zuge der Reformation wurden Bischof und Domkapitel aus Basel vertrieben. Die Bischöfe residierten seit 1527 in Pruntrut (Porrentruy), das Domkapitel in Freiburg im Breisgau, ab 1678 in Arlesheim.",
    "identifiers": {
      "Factgrid": "Q153251",
      "Gemeinsame Normdatei (GND) ID": "2029618-6",
      "Wikipedia-Artikel": "Bistum Basel",
      "Wikidata": "Q182492",
      "VIAF-ID": "131932928",
      "Catholic Hierarchy, Diocese": "dbase.html"
    },
    "identifiersComment": "Alle Normdaten nehmen sowohl auf das Fürstbistum als auch auf das heutige Bistum Basel Bezug."
  }
}
```

### Listenabfrage
Die URL für die Abfrage einer Liste von Bistümern lautet: 
`https://wiag-vocab.adw-goe.de/data/dioc?format=[Json|Csv|Rdf|Jsonld]`.
Optional kann nach dem Namen des Bistums gesucht werden durch den Parameter `name`: 
`https://wiag-vocab.adw-goe.de/data/dioc?name=[name]&format=[Json|Csv|Rdf|Jsonld]`.

Beispiel:  
<https://wiag-vocab.adw-goe.de/data/dioc?format=Json>  
<https://wiag-vocab.adw-goe.de/data/dioc?format=Csv>  
<https://wiag-vocab.adw-goe.de/data/dioc?name=Basel&format=Json>

#### Struktur
Das JSON-Dokument enthält ein Element `dioceses`, mit einer Liste der Datensätze.

Beispiel:
```json
{"dioceses":
 [ ...
  {"wiagId":"WIAG-Inst-DIOCGatz-002-001",
   "URI":"https://wiag-vocab.adw-goe.de/id/WIAG-Inst-DIOCGatz-002-001",
   "name":"Bamberg",
   "status":"Bistum",
   "dateOfFounding":"1007",
   "dateOfDissolution":"1802/1803",
   "altLabels":[{"altLabel":"ecclesia Bambergensis",
		 "lang":"la"},
		{"altLabel":"Bistum Bamberg",
		 "lang":"de"},{"altLabel":"Bambergensis diocesis",
			       "lang":"la"},
		{"altLabel":"Bamberg. dioc.",
		 "lang":"la"},
		{"altLabel":"Bishopric of Bamberg",
		 "lang":"en"},
		{"altLabel":"Dioc\u00e8se de Bamberg",
		 "lang":"fr"},
		{"altLabel":"Bisdom Bamberg",
		 "lang":"nl"}],
   "note":"Bei der Neuerrichtung des Bistums erhielt Bamberg 1818 den Rang eines Erzbistums.",
   "ecclesiasticalProvince":"exemt",
   "bishopricSeat":"Bamberg",
   "identifier":{"GND":"1080665633",
		 "VIAF":"28145193198870460903",
		 "Wikidata":"Q251337",
		 "Wikipedia":"https://de.wikipedia.org/wiki/Erzbistum_Bamberg"},
   "identifiersComment":"Wikidata und Wikipedia nehmen sowohl auf das Fürstbistum als auch auf das heutige Erzbistum Bamberg Bezug.",
   "references":[{"citation":"Erwin Gatz (Hg.)/Brodkorb, Clemens/Flachenecker, Helmut (Bearb.), Die Bistümer des Heiligen Römischen Reiches. Von ihren Anfängen bis zur Säkularisation, Freiburg i. Br. 2003",
		  "shortTitle":"Gatz, Bist\u00fcmer",
		  "authorOrEditor":"Gatz, Erwin (Hg.)/Brodkorb, Clemens/ Flachenecker/Helmut (Bearb.)","RiOpac":"http://opac.regesta-imperii.de/id/587094",
		  "page":"70-81"}]}
 ]
 ...
}

```

## HTML-Ausgabe über GET-Requests

Die Parameter für eine gefilterte Augabe in HTML können neben der Eingabe in das
Formular auch an die URL für das entsprechende Corpus angehängt werden.

Folgende URLs können verwendet werden:

„Bischöfe Gatz“

- https://wiag-vocab.adw-goe.de/query/epc
- https://wiag-vocab.adw-goe.de/bischoefe
- https://wiag-vocab.adw-goe.de/api/bischoefe

„Domherren-Datenbank“

- https://wiag-vocab.adw-goe.de/query/can
- https://wiag-vocab.adw-goe.de/domherren
- https://wiag-vocab.adw-goe.de/api/domherren


„Bistümer Gatz“

- https://wiag-vocab.adw-goe.de/query/dioc
- https://wiag-vocab.adw-goe.de/bistuemer
- https://wiag-vocab.adw-goe.de/api/bistuemer


Die Paramter entsprechen dabei den Eingabefeldern der Suchmaske in der Abfrage:

- „Bischöfe Gatz“: name, diocese, office, year, someid
- „Domherren-Datenbank“: name, domstift, office, year, someid
- „Bistümer Gatz“: name

Beispiele:

- „Bischöfe Gatz“: <https://wiag-vocab.adw-goe.de/person/query/epc?office=Administrator>
- „Domherren-Datenbank“: <https://wiag-vocab.adw-goe.de/person/query/can?year=1530>
- „Bistümer Gatz“: <https://wiag-vocab.adw-goe.de/diocese/query?name=Basel>
