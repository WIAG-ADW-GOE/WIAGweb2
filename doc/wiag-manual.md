## Einleitung

WIAG ist eine frei verfügbare, browserbasierte Anwendung und stellt
Forschungsdaten aus Sach- und Schriftquellen des Mittelalters und der
Frühen Neuzeit zur Verfügung. Die Forschungsdaten werden gut
erreichbar, interoperabel und nachnutzbar aufbereitet und
veröffentlicht. Eingebettet in eine fachspezifische Wissensplattform,
ermöglicht ein Redaktionssystem die Strukturierung, Standardisierung
und Bereitstellung von Forschungsdaten.

Mit den umfangreichen Datensammlungen der Akademieprojekte
[Deutsche Inschriften (DI)](https://adw-goe.de/forschung/forschungsprojekte-akademienprogramm/deutsche-inschriften/) und
[Germania Sacra](https://adw-goe.de/forschung/forschungsprojekte-akademienprogramm/germania-sacra/)
besteht eine zentrale Wissensbasis für die Mittelalter- und Frühneuzeitforschung.

## Datenabfrage

WIAG gliedert sich nach Themen, bzw. nach Corpora.
In der Abfrage spiegelt sich diese Gliederung wider in den Menüeinträgen unter dem
Hauptmenüpunkt „Datensammlungen“, bzw. in den Schaltflächen auf der Einstiegsseite.
Suche und Navigation sind über die Themenfelder hinweg in ähnlicher Art und Weise
aufgebaut. Die Themenfelder umfassen:

- Die Patriarchen, Erzbischöfe, Bischöfe, Weihbischöfe und weitere diözesane Leitungspersonen des Alten Reiches (nach Erwin Gatz),
- Die Erzbistümer und Bistümer des Alten Reiches (nach Erwin Gatz),
- Domherren des Alten Reiches,
- Priester des Bistums Utrecht.

Nach dem Aufruf eines der Themen erscheint im oberen Teil der Seite eine
Suchmaske mit mehreren Filterfeldern. Die Filter sind untereinander auf der Ebene der
Personen UND-verknüpft,
das heißt, ein Element in der Trefferliste muss alle Kriterien erfüllen.
Eine Ausnahme sind die Felder **Erzbistum/Bistum** und **Amt**. Sie sind auf der
Ebene eines Amtseintrages UND-verknüpft, das heißt, eine Person wird nur dann in die
Trefferliste aufgenommen, wenn sie ein Amt mit der gesuchten Bezeichnung in einem
Bistum mit der gesuchten Bezeichnung innehatte.
Es werden generell auch Teilzeichenketten in den Filterfeldern akzeptiert. Die Eingabe „berg“ findet
also sowohl „Henneberg“ als auch „Arnbergh“ oder „Bergheim“.

### Suchmaske

Das Feld **Name** prüft Kombinationen von Vorname, Namenspräfix und Familienname, sowie
Namensvarianten und Namenszusätzen. Damit die Suche effizient ist, wird die technische Hilfstabelle
`name_lookup` verwendet. Die Tabelle verzeichnet Kombinationen aus Vornamen und
Familiennamen mit den jeweiligen Varianten. Die Suche prüft auf eine Kombination der
eingegebenen Wörter in einer beliebigen Reihenfolge.
Es kann Joseph Dominikus Reichsgraf von Lamberg auch gefunden werden,
indem man lediglich „Joseph Lamberg“ in das Feld „Name“ eingibt.

Weiteres Beispiel

- Johann von Eppes
- Johann von Eps
- Johann von Aps
- Jean von Eppes
- Jean von Eps
- Johann Eppes
- Johann Eps
- Johann Aps
- Jean Eppes
- Jean Eps

Das Feld **Erzbistum/Bistum** sucht Personen aus dem Themenfeld „Bischof“, die mindestens ein Amt in dem
betreffenden Bistum haben. Diesem Feld entspricht für das Themenfeld „Domherr“ das Feld **Domstift**.

Das Feld **Amt** prüft auf Personen, die mindestens ein Amt mit der betreffenden
Bezeichnung innehatten. Die Felder **Erzbistum/Bistum** und **Amt** sind, wie oben
beschrieben, auf der Ebenen eines Amtseintrags UND-verknüpft.

Das Feld **Jahr** prüft auf Personen, deren Amtsausübung oder Lebensspanne das
angegebene Jahr umfasst. Toleranzwert ist 1. Siehe `ItemRepository::MARGIN_YEAR` und
`CanonLookupRepository::MARGIN_YEAR`.

Das Feld **Nummer** prüft auf Personen, deren WIAG ID die angegebene Nummer/ID
enthält. Es werden auch Einträge gefunden, wenn eine externe ID (GND,
Digitales Personenregister der Germania Sacra, Wikidata, VIAF)
die angegeben Nummer/ID enthält oder mit ihr übereinstimmt.

Nach Priestern des Bistums Utrecht kann auch nach **Geburtsort** und
Ordenszugehörigkeit über das Feld **Orden** gesucht werden.

Die Abfrage verwendet das [Formularsystem](https://symfony.com/doc/current/forms.html)
von Symfony und wertet POST-Requests aus.

Die Suche kann für die Themen „Bischof“ und „Domherr“ über sogenannte Facetten
verfeinert werden. Die Facetten schränken die Suche ein in Bezug auf eine oder
mehrere der Folgenden Kriterien: Bistum, Domstift, Amt, Ort oder externe URL.
Facetten ermöglichen es, verschiedene
Alternativen zu kombinieren, also mit logisch ODER zu verknüpfen, um zum Beispiel die
Domherren aus den Domstiften Lübeck und Osnabrück zusammen aufzurufen. Die Facetten
liefern zugleich eine quantitative Übersicht über die Ergebnislisten, indem sie die
jeweiligen Trefferzahlen auflisten.

### Ansichten/Navigation

In jedem der Themenfelder zeigt WIAG als Ergebnis der Suche eine Liste der Treffer
mit einigen ausgewählten Informationen an. Zu jedem Treffer kann eine Detailansicht
mit den vollstängigen Angaben geöffnet werden. In der Detailansicht sind auch die
jeweiligen Referenzwerke angegeben, aus denen die Amtsangaben entnommen sind.
Referenzwerke, die sich auf das Digitale Personenregister beziehen, bzw. von dort
übernommen wurden, werden nur angezeigt, wenn der entsprechende Eintrag einen Verweis
auf ein Biogramm enthält (fett ausgeprägt). Falls der Band mit dem Biogramm eine 'Freigabe GS Digital'
hat, verweist ein Link auf das Biogramm im Digitalen Personenregister der Germania Sacra.

Die Daten können ferner sowohl aus der
Listenansicht heraus als auch für die Detailansicht in einem strukturierten Format
aufgerufen werden. Als Formate werden angeboten: CSV, JSON, RDF-XML, JSON-LD.
Siehe Abschnitt [Serialisierung](#serialisierung).

#### Verlinkungen

Die Detailansichten verlinken zu weiterführenden Informationen innerhalb und
außerhalb von WIAG. Ortsangaben, wie zum Beispiel der Bischofssitz von Bistümern,
verweisen auf eine Seite bei [GeoNames](https://www.geonames.org). Externe
Identifier verweisen auf Einträge bei den entsprechenden Datensammlungen. Die
wichtigsten sind:

- [Gemeinsame Normdatei der Deutschen Nationalbibliothek](https://www.dnb.de/DE/Professionell/Standardisierung/GND/gnd_node.html)
- [Digitales Personenregister der Germania Sacra](http://personendatenbank.germania-sacra.de/)
- [Wikidata](https://www.wikidata.org/wiki/Wikidata:Main_Page)
- [Virtual International Authority File (VIAF)](https://viaf.org/viaf/)
- [Wikipedia](https://de.wikipedia.org/wiki/Wikipedia:Hauptseite)

Die Amtseinträge für Bischofsämtern verweisen auf die Seite des jeweiligen Bistums
innerhalb von WIAG. Die Amtseinträge von anderen Ämtern verweisen häufig auf
Domstifte oder Klöster aus der [Klosterdatenbank der Germania
Sacra](https://klosterdatenbank.adw-goe.de/liste).

### Inhalte aus mehreren Quellen

Es gibt Personen, die in mehreren Quellen beschrieben sind. Die relevanten
Quellen sind hierbei:

- Digitales Personenregister der Germania Sacra,
- Domherren-Datenbank,
- Gatz, Bischöfe und Gatz, Bistümer

Diese Personen können je nach Quellen sowohl über das Corpus
„Bischof“ als auch über das Thema „Domherr“ gefunden werden.

Für die Anzeige des Namens und der Lebensdaten werden die Daten aus der
Gatz-Bischofsliste und aus der Domherrendatenbank priorisiert. D.h. Angaben zum Namen
aus der Personendatenbank sind nur dann sichtbar, wenn sie die einzige Quelle ist.
Beispiele: Burkard, `WIAG-Pers-EPISCGatz-05243-001`, ist in der Personendatenbank mit
„Burghard I.“ eingetragen. „Burkhard“ ist der Name, der auf der Detailseite
erscheint. Johann von Rüttich, `WIAG-Pers-CANON-90865-001`, hingegen ist nur in der
Personendatenbank verzeichnet und erscheint folglich mit dem Namen, der dort
angegeben ist. Falls unterschiedliche Angaben in den Gatz-Bischofslisten und der
Domherrendatenbank vorliegen, werden beide Versionen durch einen Schrägstrich, "/",
getrennt angegegen. Beispiel: Heinrich/Heinrich Graf von Henneberg,
`WIAG-Pers-EPISCGatz-05067-001`, oder Franz Karl Ludwig Reichsfreiherr von Boos zu
Waldeck/Karl Franz Boos von Waldeck, `WIAG-Pers-EPISCGatz-10069-001`.

Die Ämterlisten der Personen werden ebenfalls für die Anzeige nach der Quelle
priorisiert.
Detailansicht: Hier erscheinen die Ämter aus dem Digitalen Personenregister in einem
eigenen Abschnitt.
Beispiel: Heinrich Graf von Henneberg, `WIAG-Pers-EPISCGatz-05067-001`, oder Albert von Hoya,
`WIAG-Pers-EPISCGatz-05438-001`.

### chronologische Listen

Es lassen sich größere Listen, z.B. mit allen Domherren eines Domstiftes, auf einer
einzigen HTML-Seite ausgeben. Dazu dient die Schaltfläche „Liste chron.“ in der
Listenansicht der Abfrage. Die einzelnen Elemente sind mit zusätzlichen
Layout-Spezifikationen ausgezeichnet für eine Umwandlung in ein WORD-Dokument. Die
Daten können als Datei gespeichert werden und dann mittels
[Pandoc](https://pandoc.org/index.html) in ein WORD-Dokument umgewandelt werden (oder
in ein anderes Format, das Pandoc unterstützt). Pandoc ermöglicht es, das Layout in
WORD einmalig festzulegen und jedesmal wieder mit einer aktuellen Ausgabe zu
verknüpfen.

Der Pandoc-Aufruf lautet:

     pandoc -f html -t docx --reference-doc=<Vorlage.docx> -o <Ausgabe-Datei.docx> <Quell-Datei.htm>

Die Datei Vorlagen-Datei `Vorlage.docx` wird im gleichem Verzeichnis gesucht, wie die
Quell-Datei. In der Vorlagen-Datei sind die Absatz-Formate festgelegt, die in das neu
zu erstellende Dokument `Ausgabe-Datei.docx` übernommen werden.

Das HTML-Attribut für die Zuweisung von Absatz-Formaten ist `custom-style`. Siehe auch die
[Pandoc-Dokumentation](https://pandoc.org/MANUAL.html#custom-styles). Innerhalb von
WIAG ist für die Ausgabe das Template `templates/person/onepage_result.html.twig`
verantwortlich. Dort werden folgende Absatzformate verwendet: „WiagId“, „AkadTitel“, „Bemerkung“, „Aemter“, „Compact“.

### HTML-Ausgabe über parametrisierte URLs

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

- „Bischöfe Gatz“: <https://wiag-vocab.adw-goe.de/query/epc?office=Administrator>
- „Domherren-Datenbank“: <https://wiag-vocab.adw-goe.de/query/can?year=1530>
- „Bistümer Gatz“: <https://wiag-vocab.adw-goe.de/query/dioc?name=Basel>

### API

Die Dokumentation umfasst auch eine [Beschreibung des API](./wiag-api-manual) zur
Ausgabe von WIAG-Daten in strukturierten Formaten.

## Redaktion

### Formularaufbau

Die Redaktionsseiten machen die zu bearbeitenden Datensätze ähnlich wie die
Abfrageseiten über ein Formular und eine Listenausgabe zugänglich. Die Suchmaske ist
erweitert um weitere Eingabefelder, wie z.B. **Referenz**, und um Felder zu
redaktionellen Metadaten, z.B. zum Datum der letzten Änderung. In diesem Feld ist es möglich,
auch Datumsbereiche anzugeben.

Das Feld **Nummer** kann dazu genutzt werden, Personen über die ID innerhalb des
Corpus zu finden, z.B. „can-26302“. Es können auch alle Einträge für ein Corpus
gefiltert werden, indem man nur die Kennung für das Corpus, also zum Beispiel „epc“
oder „can“ angibt.

PHP beschränkt die Zahl der Eingabevariablen auf 1000. Daher werden die Eingabemasken
für die einzelnen Datensätze bei Bedarf via AJAX nachgeladen. Die Zahl der
gleichzeitig an den Server übermittelten Variablen bleibt so begrenzt. Die
Eingabmasken werden nicht über das Symfony-Formular-Modul erzeugt, sondern direkt in
den Templates aufgebaut. Die Eingabmasken sind erweiterbar, da eine Person eine
theoretisch beliebig große Anzahl von Ämtern, externen Identifiern oder Referenzen
haben kann. Die entsprechende Funktionalität ist mithilfe von
[Stimulus](https://stimulus.hotwired.dev/) umgesetzt; entsprechende Teilformulare
werden vom Server nachgeladen.

Das Redaktionsmodul umfasst Eingabemasken für

- Literatur/Referenzen,
- Institutionen für Normdaten/Externe Ressourcen,
- Attribute: frei vergebbare Attribute zu Personen oder zu Amtsangaben,
- Ämter,
- Klöster: hier wird ein Abfrage für aktuelle Daten aus der Klosterdatenbank
  angestoßen,
- Bistümer,
- Personen des Themenfelds „Bischof“,
- Personen des Themenfelds „Domherr“.

### Corpus-Zuordnung

Wenn eine Person neu aufgenommen wird, wird sie einem Corpus zugeordnet und es werden
die entsprechenden IDs erzeugt. Neue IDs entstehen auch, wenn eine Person einem neuen
Corpus zugeordnet wird und dabei die bestehende Zuordnung aufgehoben wird. Dabei
geht die Verbindung zur ehemaligen ID verloren. Der Schritt kann also in dieser
Hinsicht nicht über die Redaktionsoberfläche rückgängig gemacht werden!

### Zusammenführen von Datensätzen

Es kommt vor, dass für eine Person zwei oder mehr Einträge in WIAG aufgenommen
werden. Dann können die Inhalte in einem einzigen Eintrag zusammengeführt werden. In
jedem Schritt können nur jeweils zwei Einträge verarbeitet werden. Das Zusammenführen
wird aus dem Redaktionsformular eines der betroffenen Einträge heraus
angestoßen. Die öffentlich sichtbare ID dieses Datensatzes wird die öffentlich
sichtbare ID des Ergebnis-Datensatzes. Die öffentlich sichtbare ID des zweiten
beteiligten Datensatzes kann aber weiterhin genutzt werden, um den Ergebnisdatensatz
aufzurufen.

Die Inhalte der Namensfelder und der externen Normdaten werden in dem
 zusammengeführten Datensatz kombiniert, d.h. durch das Pipe-Zeichen '|' getrennt
 hintereinander geschrieben, es sei denn der Inhalt der Ausgangs-Datensätze ist
 identisch. Vor dem Speichern müssen die unterschiedlichen Varianten in diesen
 Feldern bereinigt und das Pipe-Zeichen '|' gelöscht werden. Andernfalls weist die Anwendung
 auf verbleibene Pipe-Zeichen '|' hin und speichert die Inhalte nicht. Im Fall von
 Feldern, die wiederholt werden können, wie Ämter oder Attribute werden die
 entsprechenen Listen erweitert.

### Daten aus dem Digitalen Personenregister

Datensätze aus dem Digitalen Personenregister können nur im Digitalen
Personenregister selbst, also nicht in WIAG, bearbeitet werden.
Damit die Amtsdaten aus dem Digitalen Personenregister effizient
in die Suche miteinbezogen werden können, werden sie für die relevanten Datensätze in
WIAG übernommen. Die Datenübernahme wird manuell im Redaktionsmenü aufgerufen.

Aktualisierung: Der Import prüft für alle bestehenden Datensätze in WIAG, ob der
entsprechende Datensatz im Digitalen Personenregister ein jüngeres Änderungsdatum
hat.
In diesem Fall werden die Daten in WIAG auf den aktuellen Stand gebracht.
Auf der Seite des Digitalen Personenregisters werden nur Datensätze betrachtet,
die online (Feld `items.status`) sind.
Datensätze, die im Digitalen Personenregister nicht mehr existieren, werden vor dem
Import-Schritt aufgelistet. Bei der Aktualisierung
werden veraltete GSN durch die aktuelle GSN, wie sie im Digitalen Personenregister
vorgefunden wird, ersetzt.

Neu zu übernehmende Einträge: Aus dem Digitalen Personenregister werden alle
Datensätze gesammelt, die ein Amt in einem der 34 WIAG-Domstifte haben. Von dieser Liste
werden alle Personen abgezogen, die schon in WIAG sind. Zusätzlich werden alle
Verweis auf eine GSN in WIAG gesammelt, für die es noch keinen Personeneintrag in
WIAG gibt. Dabei werden alle Statuswerte auf WIAG-Seite mit einbezogen.
In der Liste der GSN wird geprüft, ob
eine der GSN veraltet ist. In diesem Fall wird sie im verweisenden Datensatz
durch die aktuelle GSN ersetzt. Beim Import werden Datensätze aussortiert, für die
keine Amtsdaten im Digitalen Personenregister vorliegen.

Die in WIAG aufgenommenen Datensätze erhalten die Corpus-ID „dreg-can“ (siehe
`item_corpus.corpus_id`). Die ID in der Quelle (`gsdatenbank.items.id`) wird in das
Feld `item_corpus.id_in_source` eingetragen.

## Basisdaten

### Verwendungsnachweis

Basisdaten, wie Literatur, Institutionen für Normdaten/Externe Ressourcen und
Attributtypen werden von Pesonen und Institutionen referenziert. In den jeweiligen
Redaktionsmasken ist für jeden Eintrag angegeben, wieviele solcher Verweise es gibt.
Dies soll verhindern, dass ein Eintrag gelöscht wird, solange auf ihn verwiesen
wird.
In der Anwendung wird vorausgesetzt, entsprechende Verweise gültig sind. Andernfall tritt
ein Systemfehler auf, der von der Anwendung nicht abgefangen wird.

### Orte

Für Orte gibt es keine Eingabemaske in der Anwendung. Aus [GeoNames](geonames.org)
wurden Ortsdaten für folgende Länder eingelesen: Östereich, Belgien, Kroatien,
Tschechien, Dänemark, Estland, Frankreich, Deutschland, Italien, Lettland,
Liechtenstein, Litauen, Luxembourg, den Niederlanden, Polen, Slovenien und der
Schweiz importiert.
Einzelne weitere Orte können in die Datenbank über eine Anwendung wie phpMyAdmin oder
über ein Skript eingefügt werden, wobei die Parameter für den jeweils vorliegenden
Fall anzupassen sind.

``` sql
INSERT INTO place
    SET name = 'Győr',
    geonames_id = 3052009,
    id_in_source = '3052009',
    country_id = 348,
    country_code = 'HU'
    place_type_id = 1,
    latitude = 47.68333,
    longitude = 17.63512;
```

Aus der Tabelle `place` wird der von die von der Datenbank vergebene ID
ausgelesen. In diesem Fall '974851'. Sie wird gebraucht, um Namensvarianten in die
Tabelle `place_label` aufzunehmen.

``` sql
INSERT INTO place_label
    SET geonames_id = 3052009,
    label = 'Győr',
    lang = 'hu',
    place_id = 974851,
    is_preferred = 1,
    is_geonames_name = 1;
INSERT INTO place_label
    SET geonames_id = 3052009,
    label = 'Raab',
    lang = 'de',
    place_id = 974851,
    is_preferred = 0,
    is_geonames_name = 0;
```

## Serialisierung

Beim Aufbau ihrer Datensammlungen hat die Germania Sacra Aspekte für eine
systemunabhängige nachhaltige Sicherung berücksichtigt. So bieten die Ressourcen
umfangreiche kuratierte Datenkorpora für die Geschichtswissenschaften und
benachbarte, historisch arbeitende Disziplinen. Die Datenkorpora sind hoch
strukturiert und mit Normdaten referenziert.

Indem die Datensammlungen oder für eine interessierte Person relevante Teile in
strukturierter Form über eine Serialisierung bereitgestellt werden, können die Daten
für geschichtswissenschaftliche Forschung mit unterschiedlichen Fragestellungen oder
in neuen Bezügen ausgewertet werden. Die Daten sollen dabei möglichst ohne
Informationsverlust in der serialisierten Form vorliegen. Gleichzeitig soll die
Struktur nur so kompliziert sein, wie es mindestens erforderlich ist, um die
Informationsgehalt zu erhalten. Für unterschiedliche Nutzergruppen
werden unterschiedliche Angebote gemacht.

Jedes Abfrageergebnis kann zusätzlich zu der HTML-Ausgabe in einem der Formate CSV,
JSON, RDF-XML oder JSON-LD heruntergeladen werden. 
WIAG spiegelt Daten aus der Personendatenbank der Germania Sacra und aus der
Klosterdatenbank und macht sie ebenfalls über die serialisierte Ausgabe zugänglich.

### semantische Auszeichnung

Die Ziele Einfachheit und Informationsgehalt bestimmen die Auswahl der Ontologien,
welche für die Serialisierung genutzt werden. Weitere Kriterien sind
Beständigkeit und Verbreitung. Da jede bestehende Ontologie einen eigenen Fokus hat,
oder für ein bestimmtes Themengebiet entwickelt wurde, decken sich die
Begrifflichkeiten nicht zu hundert Prozent mit den Inhalten der Forschungsdaten der
Germania Sacra. Meistens sind die Abweichungen aber tolerierbar und die Gefahr von
Fehlinterpretationen sind gering. In Bezug auf strukturelle Fragen ergibt sich die
gleiche Problematik, zumal hier Dokumentationen zu den Ontologien nicht immer
eindeutig sind.

#### Gemeinsame Normdatei

https://d-nb.info/standards/elementset/gnd   
Die Gemeinsame Normdatei wird von der Deutschen Nationalbibliothek
bereitgestellt. Sie ist ein Instrument, um Personen, Veranstaltungen und
Institutionen zu identifizieren, damit Mehrdeutigkeiten vermieden werden können. Zur
GND ist eine Ontologie entwickelt worden. Sie ist eine Formatspezifikation für die
Beschreibung von GND-Entitäten im semantischen Web. 

Die GND-Ontologie zeichnet sich durch einen hohen Standardisierungsgrad aus und deckt
viele Aspekte von für die Germania-Sacra relevanten Entitäten ab. Sie umfasst Klassen
(Classes), Objekteigenschaften (Object Properties), Datentyp-Eigenschaften und
Annotations-Eigenschaften (Annotation Properties).

Elemente der Gemeinsamen Normdatei sind im Folgenden mit `gndo:` gekennzeichnet.

#### Friend of a Friend

http://xmlns.com/foaf/0.1/   
Die Entwicklung der Friend of a Friend-Ontologie (FOAF) war motiviert durch die
Nutzung von Kontaktdaten, die Menschen in sozialen Netzwerken verbinden. Die
Ontologie gliedert sich in Klassen (Classes) und Eigenschaften (Properties). Der
Kernbestand (Core) umfasst Eigenschaften von Personen und sozialen Gruppen, die wenig
Änderungen unterworfen sind.

Elemente aus Friend of a Friend sind im Folgenden mit `foaf:` gekennzeichnet.

#### Web Ontology Language

http://www.w3.org/2002/07/owl#   
Die Web Ontology Language (OWL) ist eine Spezifikation zur Beschreibung von
Ontologien, umfasst aber selbst auch eine Ontologie.

Elemente der Web Ontology Language sind im Folgenden mit `owl:` gekennzeichnet.

#### Schema.org

https://schema.org/   
Die Initiative Schema.org geht auf große IT/Internet-Konzerne zurück. Die Initiative
entwickelt auch die Spezifikation Microdata, die es erlaubt, Metadaten direkt in
HTML-Dokumente einzubetten.

Elemente aus schema.org sind im Folgenden mit `schema:` gekennzeichnet.

### Mapping der Daten

- `person` -- `gndo:DifferentiatedPerson`  
  Typ des Wurzelknotens, der Struktur, welche eine Person beschreibt
- `person.givenname`, `person.prefixname`, `person.familyname` -- `gndo:preferredName`  
  Die Elemente aus WIAG werden zu einer einzigen Zeichenkette zusammengefügt.
- -- `gndo:preferredNameEntityForThePerson`  
  Strukturknoten für Elemente des Namens einer Person
- `person.givenname` -- `gndo:forename`
- `person.prefixname` -- `gndo:prefix`
- `person.familyname` -- `gndo:surname`
- -- `gndo:variantNameEntityForThePerson`  
  Strukturknoten für Elemente eines alternativen Namens einer Person
- `givenname_variant.name`  -- `gndo:forname`
- `familyname_variant.name` -- `gndo:surname`
- `item.academic_title`, `item.notePerson` -- `gndo:biographicalOrHistoricalInformation`
- `url_external.value` -- `gndo:gndIdentifier`  
  sofern `url_external.authority_id` auf die GND verweist
- `url_external.value` -- `foaf:page`  
  sofern `url_external.authority_id` auf Wikipedia verweist
- `url_external.value` -- `owl:sameAs`  
  sofern `url_external.authority_id` auf eine bestimme Auswahl von Institutionen verweist
- -- `schema:hasOccupation` --	Strukturknoten für Amtsdaten
- -- `schema:Role`  
  Typ des Strukturknotens für Amtsdaten
- `role_name` oder `person_role.role_name` -- `schema:roleName`  
  Für normierte Amtsbezeichnungen wird `role.name` ausgegeben.
- `person_role.date_begin`-- `schema:startDate`
- `person_role.date_end`-- `schema:endDate`
- -- `schema:affiliation`  
  Strukturknoten für Angaben zu einem Domstift, Kloster oder Bistum
- `institution.name` oder `person_role.institution_name` -- `schmema:name`
- `instituion.id_gsn` -- `schema:url`  
  URI in der Klosterdatenbank im Falle eines Domstiftes oder Klosters
- `item_corpus.id_public` -- `schmem:url`  
  URI in WIAG im Falle eines Bistums

### Datenpakete

Alle Datenpakete lassen sich aus der Listenansicht eines Abfrageergebnisses
erzeugen über die Schaltflächen „CSV“, „RDF-XML“, „JSON“, „JSON-LD“ und „Export“.
Der Umfang des Datenpakets entspricht dem Ergebnis der aktuellen Abfrage. Die Ausgabe
der Datenpakete ist wegen des Ressourcenverbrauchs auf dem Server auf eine
Trefferliste von maximal 60000 beschränkt. Diese Beschränkung gilt nicht für Ausgaben
über die Schaltfläche „Export“, da hier die Daten gestreamt werden. 
Die Dateinamen enthalten das Themengebiet,
zum Beispiel "Bischöfe", "Domherren", "Priests-of-Utrecht" 
und je nach Format die Dateiendung "xml", "json" oder "csv".

Das CSV-Format wird in zwei Varianten bereitgestellt. In der ersten Variante
(Schaltfläche „CSV“) entspricht jedem Element/Feld eines Datensatzes eine Spalte in der
CSV-Datei. 1-n-Beziehungen werden in eine Sequenz von Spalten aufgelöst, wobei
der Spaltenname die Listenposition enthält. Die Zählung beginnt mit 0.
Beispiel: „offices.9.dateBegin“
enthält das Start-Datum des 10. Amtseintrages, „offices.9.references.0.citation“
enthält die bibliographischen Angaben des ersten Literaturverweises für
die 10. Amtsperiode. Dieses Format führt zu einer großen Zahl von Spalten.

Das zweite Format (Schaltfläche „Export“) unterteilt die Daten und bildet die ersten Ebenen der relationalen
Struktur ab. Die Daten sind so für eine manuelle Sichtung und Weiterverarbeitung
leichter zugänglich.
Die Daten sind unterteilt in

- „CSV Personendaten“: WIAG ID, Namen, Beschreibung, allgemeine biographische Daten, Normdaten
- „CSV Amtsdaten“: WIAG ID, Amtsdaten, Normdaten
- „CSV Literatur Personen“: WIAG ID, Literaturverweise bezogen auf die Person, Normdaten
- „CSV Externe IDs“: WIAG ID, sämtliche externe IDs, Normdaten

Über die WIAG ID können die Daten in aufnehmenden Systemen wieder zugeordnet werden. Für
allfällige Abgleiche werden jeweils Normdaten (GND, Wikidata, FactGrid)
mit ausgegeben.

Der Aufbau der Ausgabedateien in den übrigen Formaten ist beschrieben in der Dokumentation zum
[Application Programming Interface](./wiag-api-manual.md).

### Import in FactGrid

Das Gothaer [FactGrid](https://database.factgrid.de/wiki/Main_Page) ist eine
[Wikibase](https://www.wikimedia.de/projects/wikibase/)-Instanz, die sich speziell
Projekten der „Digital Humanities“ als breite
Plattform anbietet. Benutzer können Datenbank-Objekte
anlegen und mit beliebigen Aussagen ihres Forschungsinteresses ausstatten, die sie
dann auswerten und unterschiedlich visualisieren.
Wikibase ist die Software, die auch [Wikidata](https://www.wikidata.org) nutzt.
FactGrid will historischer Forschung einen eigenen Freiraum zur Verfügung stellen und
technisch in der Lage sein, mit der GND und mit Wikidata laufend Daten
auszutauschen — Daten, die aus dem FactGrid heraus international als Forschungsdaten
zitierbar werden. Seit dem 5. November 2022 ist das FactGrid — als global agierende
Plattform — offizielles Repositorium im Spektrum der deutschen Nationalen
Forschungsinfrastruktur, [NFDI4Memory](https://4memory.de/).
Im FactGrid werden Informationen angelehnt an RDF als Einzelaussagen, Statements,
abgelegt. Jedes Statement bezieht sich auf ein Objekt, beispielsweise auf eine
Person. Für den Import in das FactGrid müssen die Objekte über ihre ID in FactGrid
identifiziert werden, sofern es sich nicht aum Objekte handelt, die neu in FactGrid
angelegt werden.

Beim Import aus WIAG wird ein Objekt
(Person, Institution, Amt, etc.) zusammen mit grundlegende Daten,
wie Name, Beschreibung, WIAG-ID und Normdaten eingetragen. Vor allen weiteren
Importschritten wird zunächst aus dem FactGrid die FactGrid ID ausgelesen und wiederum
in WIAG aufgenommen, damit die IDs den Objekten zugeordnet und für den Import
angegeben werden können.
Technisch werden die Informationen aus WIAG als CSV-Datei (Export-Format) ausgelesen
und mit einem externen Skript in ein Format für das WikiData-Tool
[QuickStatements](https://www.wikidata.org/wiki/Help:QuickStatements)
umgewandelt. So lassen sich die einzelnen Schritte besser kontrollieren und
weiterentwickeln im Vergleich zu einem vollautomatischen Import über die
Webanwendung.

#### Zuordnung von Properties

Im FactGrid ist eine 
[Übersicht über die Properties](https://database.factgrid.de/wiki/FactGrid:Directory_of_Properties)
abrufbar.

Die Daten der Bischöfe nach Gatz, der Domherren aus der Domherren-Datenbank oder aus
dem Digitalen Personenregister werden wie folgt zugeordnet.

- `person.givenname`, `person.prefixname`, `person.familyname` -- Label: Lde, Len, 
  Lfr, Les (FactGrid-Kennungen)  
  Die Elemente der Quelle werden zu einer Zeichenkette zusammengefügt und als
  Label gekennzeichnet, jeweils für die Sprachen Deutsch, Englisch, Französisch und
  Spanisch.
- `person.date_birth`, `person.date_death`, `person_role.*` -- Description: Dde, Den,
  Dfr, Des  
  Das Geburts- und das Sterbedatum werden zu einer Zeichenkette zusammengefügt und 
  als Beschreibungstext gekennzeichnet. 
  So kann ein menschlicher Nutzer die Person schnell zeitlich
  einordnen. Falls Geburts- und Sterbedatum nicht vorliegen, wird ein erstes Datum aus
  den Amtszeiten angegeben.
  Für die Beschreibung in Deutsch werden zusätzlich bis zu zwei Amtsangaben mit in
  den Text aufgenommen. Die Ämter folgender Gruppen werden priorisiert:
  „Oberstes Leitungsamt Diözese“, „Leitungsamt Domstift“.
  Das zweite Sortierkriterium ist die Amtszeit, wobei zuletzt ausgeübte Ämter
  priorisiert werden. Die Ämter aus dem Digitalen Personenregister werden nur dann
  berücksichtigt, wenn sie das Digitale Personenregister die einzige Quelle für die
  Person ist.

Folgende Properties haben für alle Personen den gleichen Wert:

- P2: Ist ein(e) -- Q7: Mensch
- P131: Forschungsprojekte, die zu diesem Datensatz beitrugen -- Q153178: Germania Sacra im FactGrid
- P154: Geschlecht -- Q18: Männliches Geschlecht

Weiter werden folgende Identifier übertragen:

- `item.id` (identisch mit `person.id`) -- P601: WIAG ID 
- `url_external.value` für verschiedene Werte von `url_external.authority_id`
  - P76: GND ID
  - P472: Personendatenbank Germania Sacra ID
  - Swikidatawiki: Wikidata ID
  - Sdewiki: Eintrag in der deutschen Wikipedia

## Einfügen eines neuen Corpus

Damit ein Corpus in WIAG aufgenommen werden kann, muss es eine kompatible
Grundstruktur aufweisen: Lebensdaten von Personen, Daten zu Ämtern in Bistümern,
Domstiften oder Klöstern, Verweise auf Literaturquellen und optional externe
Identifier. Wenn diese Daten tabellarisch in CSV-Dateien vorliegen können sie über
ein Jupyter-Notebook in die WIAG-Struktur überführt werden. Zusätzlich muss die
Web-Anwendung auf das neue Corpus angepasst werden.


### Anpassungen in der Web-Anwendung

#### Corpus bekannt machen
Die Tabelle `corpus` verzeichnet die für die Anwendung erforderlichen Daten. Das Feld
`corpus_id` enthält den Schlüssel, über den das Corpus in der Anwendung identifiziert
wird, z.B. `dioc`, `epc`.
Außerdem definiert die Datei `src/Entity/Corpus.php` die Liste editierbarer Corpora
und die Liste von Corpora, deren Mitglieder eine öffentliche ID haben.

```
     // editable corpora
    const EDIT_LIST = ['dioc', 'can', 'epc', 'ibe'];
    // corpora that correspond to themes
    // The order of this list reflects the priority of public IDs.
    const PUBLIC_LIST = ['dioc', 'ibe', 'utp', 'epc', 'can', 'dreg-can'];
```

Ein neues Corpus ist hier einzutragen mit seiner Corpus-ID. (`corpus.corpus_id`).

#### Einstiegsseite, Menü und Abfrageformular
Die Datei `templates/home/home.html` enthält für jedes abfragbare Corpus einen
Abschnitt mit einem Bild, einem Link auf die entsprechende Abfrage-Seite und eine
Kurzbeschreibung. Für ein neues Corpus ist ein solcher Abschnitt an der gewünschten
Stelle einzufügen.

Die Datei `templates/_main_menu.html` enthält das Dropdown-Menü für die
Hauptnavigation. Hier ist in den Abschnitten "collections, Datensammlungen" und
"edit" ein Link für die Abfrage des Corpus, bzw. für die Redaktion des Corpus
einzufügen.

Der Umfang des Abfrageformulars und seiner Filter wird in der Datei
`src/Form/PersonFormType.php` festgelegt. Dazu dient die Konstante `FILTER_MAP`.

```
    const FILTER_MAP = [
        'can' => ['cap', 'ofc', 'plc', 'url'],
        'epc' => ['dioc', 'ofc', 'url'],
        'ibe' => ['dioc', 'ofc'],
    ];

```

Das Formular für das Corpus "can, Domherren-DB" umfasst Felder für eine Abfrage nach
Domstift ('cap'), Amt ('ofc'), Ort ('plc') und Externe ID ('url'). Bischöfe werden
nicht nach Domstift, sondern nach Diözese ('dioc') abgefragt.

TODO: Rechte security.yaml

### Datengrundlage

- Themenbild z.B. 1200 zu 770 Pixel

### Daten in die WIAG-Struktur aufnehmen
### Elemente des Corpus online stellen

## Entwickler-Dokumentation

### Inhaltliche Struktur und Datenmodell

#### Themen

WIAG gliedert sich nach Themen, bzw. nach Corpora. Bisher umfasst die Anwendung Bistümer
des Alten Reiches, Bischöfe des Alten Reiches nach Gatz, Domherren des Alten
Reiches aus der entsprechenden Datenbank der Germania Sacra, Priester der
mittelalterlichen Diözese Utrecht und Bischöfe der iberischen Halbinsel. 
Ergänzend werden Amtsdaten aus dem Digitalen
Personenregister der Germania Sacra angezeigt.
In der Abfrage spiegelt sich diese Gliederung wider in den Menüeinträgen unter dem
Hauptmenüpunkt „Datensammlungen“, bzw. in den Schaltflächen auf der Einstiegsseite.

Den Corpora entsprechen datentechnisch Einträge in der der Tabelle
`corpus`. Die Tabelle enthält weitere Einträge zu Entitäten, welche
für die Erfassung und Beschreibung der Karrierewege von Personen
genutzt werden: Kloster, Domstift, religiöser Orden und Amt.

Auszug aus `corpus`.

| id | corpus_id | name                                   | note                                                                        |
|---:|:----------|:---------------------------------------|:----------------------------------------------------------------------------|
|  1 | dioc      | Diözesen                               | Diözesen des Alten Reiches nach Gatz                                        |
|  2 | mon       | Klosterdatenbank                       | Klosterdatenbank der Germania Sacra                                         |
|  3 | cap       | Domstift                               | Domstifte                                                                   |
|  4 | epc       | Bischöfe Gatz                          | Bischofsdatenbank mit Bischöfen und Patriarchen des Alten Reiches nach Gatz |
|  5 | can       | Domherren-DB                           | Domherrendatenbank mit Domherren des Alten Reiches                          |
|  6 | dreg-can  | Digitales Personenregister - Domherren | Domherren im Digitalen Personenregister der Germania Sacra                  |
|  7 | dreg      | Digitales Personenregister - allgemein | Digitales Personenregister der Germania Sacra                               |
|  8 | ord       | Ordensliste                            | religiöse Orden                                                             |
|  9 | ofcm      | Ämterliste                             | normierte Ämterliste                                                        |
| 10 | canf      | weibliche Geistliche                   | weibliche Geistliche, z.B. Äbtissinen                                       |
| 11 | utp       | Priester von Utrecht                   | Priesterweihen in der mittelalterlichen Diözese Utrecht nach Rombert Stapel |
| 13 | ibe       | Bischöfe Iberische Halbinsel | Datenbank zu Bischöfen der iberischen Halbinsel im Mittelalter (bis ins 13. Jahrhundert) |

#### Datenmodell

Das Datenmodell lässt sich gruppieren in Tabllen zu  Metadaten, technische Hilfsdaten und zu den eigenlichten Inhalten. Metadaten zum Redaktionsprozess enthalten die Tabellen:
`user_wiag`, `item`, `corpus`, `item_property_type`, `place_type`

Technische Hilfstabellen, z.B. um Abfragen effizienter zu machen, sind die Tabellen
`name_lookup` und `item_name_role`.

Die restlichen Tabellen enthalten Eigenschaften zu Personen und deren Karrieren. Eine
vollständige Übersicht findet sich in ein einem ergänzenden
[Dokument](./wiag-db-doc.md)
zu diesem Handbuch.

### Datenorganisation bei mehreren Quellen

Die Angaben zu Ämtern aus mehreren Quellen werden über Einträge in der
Tabelle `item_name_role` zusammengeführt, die ihrerseits auf Verweisen in
`url_external` basiert.

Die Tabelle `item_name_role` hat folgende Struktur:

| id     | item_id_name | item_id_role |
|:-------|-------------:|-------------:|
| 315846 |        14356 |        14356 |
| 315847 |        14356 |        19386 |
| 315848 |        13090 |        13090 |
| 315849 |        13090 |        28343 |
| 315850 |        15571 |        15571 |
| 315851 |        15571 |        19387 |

`id` ist eine fortlaufende tabelleneigene ID. `itme_id_name` ist die ID der Person
aus der Quelle, deren Namensdaten verwendet werden. `item_id_role` ist die ID der
Person aus der Quelle, deren Amtsdaten verwendet werden.
Als zusätzliche Quelle kommt aktuell nur das Digitale Personenregister
vor. Beispielsweise kann ein Eintrag in `item_id_name` zum Corpus Domherrendatenbank
gehören und ein Eintrag in `item_id_role` zum Corpus Digitales Personenregister. In
den meisten Fällen sind die Einträge in den beiden Spalten gleich.

### Framework

Die Webanwendung WIAG ist als ein sogenannter LAMP-Stack aufgebaut:
Auf einem UNIX- oder Linux-artigen Betriebsystem läuft ein
[Apache Webserver](https://httpd.apache.org/). Die Daten werden in einer
[MySQL-Datenbank](https://dev.mysql.com/)  verwaltet
(alternativ [MariaDb](https://mariadb.org/)), und ein
[PHP-Framework](https://www.php.net) wird verwendet, um
die Seiten der Anwendung  zu gestalten und mit Daten zu versorgen.

Für WIAG ist das PHP-Framework [Symfony](https://symfony.com/) im
Einsatz. Die aktuelle WIAG-Version von Symfony ist
5.4.22 (Long-Term Support Release mit Sicherheits-Patches bis Ende
2025).

WIAG verwendet [TWIG](https://twig.symfony.com/)-Templates und
[Bootstrap](https://getbootstrap.com/) für die Gestaltung der
Seiten. JavaScript wird über das Webpack API
[Webpack Encore](https://symfony.com/doc/current/frontend.html) und
[Stimulus](https://stimulus.hotwired.dev/) eingebunden.

WIAG übernimmt die von Symfony vorgegebene Verzeichnisstruktur.

- `src/Controller`: Klassen, welche die Anfragen entgegennehmen und eine HTML-Seite
  zurückliefern.
- `src/Entity`: Datencontainer mit Zugriffsfunktionen und Funktionen zur Kombination
  von Datenfeldern. Im Allgemeinen entspricht jeweils eine Klasse einer Tabelle in
  der Datenbank.
- `src/Repository`: Klassen zur Abfrage der Datenbank.
- `src/Service`: Hilfsklassen mit Funktionen, welche die Controller-Klassen für
  bestimmte Aufgaben unterstützen.
- `src/Form`: Formulare für Suchabfragen.
- `src/Form/Model`: Datencontainer für Suchabfragen.
- `templates`: TWIG-Templates mit dem Seitenaufbau.
- `assets/styles/app.scss`: CSS-Klassen und -Parameter.
- `assets/controllers`: Stimulus Controller (JavaScript).

#### WIAG installieren

Die Web-Anwendung verwendet eine MySQL-Datenbank oder MariaDB.
Die Datenbank ist entsprechend dem [Datenmodell](#datenmodell) aufzusetzen und zu befüllen.

Der Quellcode für WIAG liegt in einem GitHub-Repository und kann von dort bezogen werden. Man wechselt in ein geeignetes Verzeichnis und kopiert die Quellen von dort mit:

    git clone https://github.com/WIAG-ADW-GOE/WIAGweb2.git

Es wird ein Verzeichnis `WIAGweb2` angelegt. Es enthält die Datei `.env`. In diese Datei sind die Angaben zum Datenbank-Zugang einzutragen.

    DATABASE_URL="mysql://db_user@127.0.0.1:3306/db_name?serverVersion=5.7"
	DATABASE_PASSWORD=db_password

Für eine Produktiv-Umgebung wird das Password verschlüsselt. Siehe [Symfony Secrets](https://symfony.com/doc/current/configuration/secrets.html).

Im Verzeichnis `WIAGweb2` lädt man die Symfony-Module mit dem *PHP dependency manager* [composer](https://getcomposer.org/):

	cd WIAGweb2
    composer install

Die Node.js-Module lädt man mit dem *package manager* [yarn](https://yarnpkg.com/):

    yarn install

Es sind noch die WIAG-eigenen Style-sheets und JavaScript-Dateien zu erzeugen mit:

	cd public
	yarn build

Hierzu finden sich Erläuterungen in der Dokumentation von [Webpack encore](https://symfony.com/doc/current/frontend.html).

Ein lokaler Server wird im Projektverzeichnis von WIAG gestartet:

    cd ..
	symfony serve

Die Web-Anwendung lässt sich für Testzwecke lokal mit dem Browser öffnen unter `https:localhost:8000`.


### Sortierung von Amtsangaben

#### Chronologische Sortierung
Wenn Ämter chronologisch sortiert werden, ist zunächst das Jahr des Amtsbeginns
maßgebend; falls es nicht vorliegt, das Jahr des Amtsendes.

Um auch unscharfe Zeitangaben für die Sortierung verwendbar zu machen,
wird der Wert der Jahreszahl mit einem dreistelligen zusätzlichen Sortierschlüssel
versehen gemäß der folgenden Tabelle.

| Zeitraum/Zeitangabe          | Wert |              Sort |
|:-----------------------------|-----:|------------------:|
| unbekannt                    |      | am Ende der Liste |
| vor 1200                     | 1200 |               100 |
| kurz vor 1200                | 1200 |               105 |
| 1200                         | 1200 |               150 |
| ca. 1200                     | 1200 |               200 |
| um 1200                      | 1200 |               210 |
| erstmals erwähnt 1200        | 1200 |               110 |
| kurz nach 1200               | 1200 |               303 |
| Anfang der 1200er Jahre      | 1200 |               305 |
| nach 1200                    | 1200 |               309 |
| 1200er Jahre                 | 1200 |               310 |
| Anfang 12. Jh.               | 1199 |               500 |
| erstes Viertel des 12. Jhs.  | 1199 |               530 |
| 1. Hälfte des 12. Jhs.       | 1199 |               550 |
| frühes 12. Jh.               | 1199 |               555 |
| zweites Viertel des 12. Jhs. | 1199 |               560 |
| Mitte 12. Jh.                | 1199 |               570 |
| drittes Viertel des 12. Jhs. | 1199 |               580 |
| 2. Hälfte des 12. Jhs.       | 1199 |               590 |
| spätes 12. Jh.               | 1199 |               593 |
| Ende 12. Jh.                 | 1199 |               594 |
| viertes Viertel des 12. Jhs. | 1199 |               595 |
| 12. Jahrhundert              | 1199 |               800 |
| wohl im 12. Jahrhundert      | 1199 |               810 |
| 12. oder 13. Jahrhundert     | 1199 |               850 |

Der Sortierschlüssel ist Inhalt des Feldes `person_role.date_sort_key`.

#### Sortierung in verschiedenen Ansichten

**Listenansicht**: Die Listenansicht erscheint beim Einstieg in ein Thema/Corpus und als
Ergebnis einer Suche. Hier werden die Ämter der Bischöfe nach Gatz
beziehungsweise aus der Domherren-Datenbank ebenfalls in Listenform nebem dem Namen
einer Person dargestellt. Die Sortieung ist chronlogisch aufsteigend.


**Detailansicht**: Die Detailansicht erscheint, wenn der Nutzer dem Link einer Person in
der Listenansicht folgt, oder wenn ein Datensatz über seine ID direkt aufgerufen
wird. Die Sortieung ist chronologisch aufsteigend.

**HTML-Gesamtliste auf einer Seite**: Die HTML-Gesamtliste auf einer Seite ist für
angemeldete Benutzerinnen abrufbar. Typischerweise wird diese Liste für ein
bestimmtes Domstift erstellt. Wenn das Domstift eindeutig bestimmbar ist, erscheint
sein Name im Titel der HTML-Seite. Für jede Person werden die Ämter des
betreffenden Domstifts an oberster Stelle ausgegeben. Die Ämter an anderen Domstiften
werden sortiert nach dem Namen des Ortes, an dem das Amt ausgeübt wurde. Das zweite
Sortierkriterium ist die chronologische Ordnung.

Die Sortierkriterien werden jeweils vom Controller als ein Parameter an das Template
übergeben. Dort wird eine Sortierfunktion in 'Person.php' aufgerufen, welche die
Ämter in der sortierten Reihenfolge liefert.

### Rechtevergabe

Symfony unterstützt die Verwaltung von Benutzerrechten, in dem jedem Benutzer/jeder Benutzerin eine
oder mehrere Rollen zugewiesen werden können. Diese Zuordnungen werden über die
WIAG-Benutzerverwaltung in der Tabelle `user_wiag` eingetragen. Rollen können zu Gruppen
zusammengefasst werden, so dass ein Benutzer, der einer Gruppe zugeordnet ist, alle
Rechte der Rollen erhält, die in dieser Gruppe enthalten sind. In der Datei
`config/packages/security.yaml` sind die Gruppen festgelegt.

Folgende Rechte werden in WIAG verwendet:
- "ROLE\_EDIT\_{corpus}": Redaktion des entsprechenden Corpus'
- "ROLE\_EDIT\_ALL": Redaktion aller Corpora'
- "ROLE\_CANON\_ONEPAGE": Ausgabe von Domherren eines Domstifts gesammelt auf einer
  HTML-Seite
- "ROLE\_EDIT\_USER": Redaktion von Benutzerrechten
- "ROLE\_DATA\_EDIt": Import von Daten aus externen Quellen (Digitales
  Personenregister, Klosterdatenbank)

Im Menü für die Rechtevergabe für die einzelnen Benutzer könne alle Rollen unabhängig
voneinander angewählt oder abgewählt werden. Die Anwendung überprüft nicht, ob
Zuweisungen unnötig sind, z.B. wenn gleichzeitig ein Recht über eine Gruppe aber auch
direkt vergeben wird.

### SQL-Abfragen
Für bestimmte Auswertungen und Arbeitsschritte ist es einfacher, die Daten direkt aus 
der Datenbank abzufragen, anstelle eines Exports in ein strukturiertes Format, 
das dann anschließend wieder verknüpft oder gefiltert werden muss. 
Im Folgenden sind einige Beispiele zusammengestellt.

#### Zuordnung WIAG-ID zu GSN
Erstelle eine Übersicht für alle Personen, die auf einen Eintrag im Digitalen Personeregister 
verweisen mit ihrer ID im Digitalen Personenregister (GSN).

Die Tabelle `t_ip` wird aus einer Abfrage von `item_corpus` erzeugt. 
`item_corpus` wird mehrmals mit sich selbst verknüpft und nachher in den Wert
für die WIAG-ID auswählen zu können, der die höchste Priorität hat (siehe `CASE`-Abschnitt). 
Die ID bezogen auf einen Bischof nach Gatz wird bevorzugt vor einer ID,
die der Domherren-Datenbank zugeordnet ist, und der ID, welche auf einen Datensatz
aus dem Digitalen Personenregister verweist.
Die Verknüpfung mit `item_name_role.item_id_name` dient dazu, Einträge aus dem Digitalen Personenregister
herauszufiltern, die mit einem Bischof nach Gatz oder einem Domherren aus der Domherren-Datenbank
verknüpft sind. 
Die ID des Digitalen Personenregisters in der Tabelle `authority`, bzw. `url_external` ist 200.

``` sql
SELECT DISTINCT i.id,
CASE WHEN (t_id.epc IS NOT NULL) THEN t_id.epc
WHEN (t_id.can IS NOT NULL) THEN t_id.can
WHEN (t_id.dreg_can IS NOT NULL) THEN t_id.dreg_can
END AS id_public,
uext.value AS gsn
FROM item AS i
JOIN url_external AS uext ON uext.item_id = i.id AND uext.authority_id = 200
JOIN item_name_role AS inr ON inr.item_id_name = i.id
JOIN (select distinct ic.item_id AS item_id,
   ic_ii.id_public AS epc,
   ic_iii.id_public AS can,
   ic_iv.id_public AS dreg_can
   FROM item_corpus AS ic
   LEFT JOIN item_corpus AS ic_ii ON ic_ii.item_id = ic.item_id AND ic_ii.corpus_id = 'epc'
   LEFT JOIN item_corpus AS ic_iii ON ic_iii.item_id = ic.item_id AND ic_iii.corpus_id = 'can'
   LEFT JOIN item_corpus AS ic_iv ON ic_iv.item_id = ic.item_id AND ic_iv.corpus_id = 'dreg-can'
   WHERE ic.corpus_id in ('epc', 'can', 'dreg-can')) AS t_id ON t_id.item_id = i.id
WHERE i.is_online = 1;
```

#### Pfründe für Mainzer Dompröpste oder Domdekane
Erstelle eine Liste der Ämter von Personen, die in Mainz Dompropst oder Domdekan waren, aber ohne
die Ämter am Mainzer Domstift. Es werden keine Amtsangaben ausgelesen, wenn eine Person (nur)
im Digitalen Personenregister eingetragen ist.
Die ID des Mainzer Domstiftes in der Tabelle `institution` ist 6623.
Die Bedingung `WHERE i.id <> 6623` schließt die Ämter am Domstift aus.
Der Personenkreis wird eingeschränkt durch den Abgleich von `person_role.person_id` mit
der Abfrage nach Personen, die am Mainzer Domstift das Amt mit der ID 108, Domdekan, oder
mit der ID 150, Dompropst, innehatten. Siehe zweites `SELECT`-Statement.

``` sql
SELECT
p.givenname AS Vorname,
p.prefixname AS Präfix,
p.familyname AS Familienname,
pr.role_name AS Amtsbezeichnung,
pr.num_date_begin AS Amtsbeginn,
pr.num_date_end AS Amtsende,
i.id_gsn AS Institution_ID_GSN,
i.name AS Institution_Name,
pl.longitude AS Längengrad,
pl.latitude AS Breitengrad,
pl.name AS Ortsname,
ic.id_public
FROM person p
JOIN item_corpus ic ON ic.item_id = p.id AND ic.corpus_id = 'can'
JOIN person_role pr ON p.id = pr.person_id
JOIN institution i ON pr.institution_id = i.id
JOIN institution_place ip ON i.id = ip.institution_id
LEFT JOIN place pl ON ip.place_id = pl.id
WHERE i.id <> 6623
AND person_id in (SELECT DISTINCT person_id
   FROM person_role AS pr
   JOIN institution i ON pr.institution_id = i.id
   JOIN item on item.id = pr.person_id
   where pr.role_id IN (108, 150) AND i.id = 6623
   AND item.is_deleted = 0
   AND item.is_online = 1)
ORDER BY id_public;
```

#### Pfründe für Mainzer Bischöfe ohne das Bischofsamt in Mainz
Erstelle eine Liste der Ämter von Personen, die in Mainz das Bistum geleitet haben,
aber ohne das Leitungsamt für das Bistum in Mainz selbst.
Es werden keine Amtsangaben ausgelesen, wenn eine Person (nur)
im Digitalen Personenregister eingetragen ist.
Die ID des Mainzer Bistums in der Tabelle `diocese` ist 299.
Der Personenkreis wird eingeschränkt durch den Abgleich von `person_role.person_id` mit
der Abfrage nach Personen, die im Mainzer Bistum ein Amt innehatten,
dessen Gruppe die ID 33 oder 34 hat (`role.role_group_id`).
das Amt mit der ID 108, Domdekan, oder
mit der ID 150, Dompropst, innehatten. Siehe zweites `SELECT`-Statement.

``` sql
SELECT
p.givenname AS Vorname,
p.prefixname AS Präfix,
p.familyname AS Familienname,
ic.id_public,
pr.role_name AS Amtsbezeichnung,
pr.num_date_begin AS Amtsbeginn,
pr.num_date_end AS Amtsende,
i.id_gsn AS Institution_ID_GSN,
i.name AS Institution_Name,
pl_inst.longitude AS Institution_Laengengrad,
pl_inst.latitude AS Institution_Breitengrad,
pl_inst.name AS Domstift_Ortsname,
ic_dioc.id_public AS Dioezese_ID,
dioc.name AS Dioezese_Name,
pl_dioc.longitude AS Dioezese_Sitz_Laengengrad,
pl_dioc.latitude AS Dioezese_Sitz_Breitengrad
FROM person p
JOIN item_corpus ic ON ic.item_id = p.id and ic.corpus_id = 'epc'
JOIN person_role pr ON p.id = pr.person_id
JOIN role AS r ON r.id = pr.role_id
LEFT JOIN institution i ON pr.institution_id = i.id
LEFT JOIN institution_place ip ON i.id = ip.institution_id
LEFT JOIN place pl_inst ON pl_inst.id = ip.place_id
LEFT JOIN diocese AS dioc ON dioc.id = pr.diocese_id
LEFT JOIN item_corpus AS ic_dioc ON ic_dioc.item_id = dioc.id
LEFT JOIN place pl_dioc ON pl_dioc.id = dioc.bishopric_seat_id
WHERE NOT (dioc.id = 299 AND r.role_group_id in (33, 34))
AND person_id in (SELECT DISTINCT person_id
   FROM person_role AS pr
   JOIN diocese AS dioc ON dioc.id = pr.diocese_id
   JOIN item ON item.id = pr.person_id
   JOIN role AS r ON r.id = pr.role_id
   WHERE r.role_group_id IN (33, 34) AND dioc.id = 299
   AND item.is_deleted = 0
   AND item.is_online = 1)
ORDER BY id_public;
```
