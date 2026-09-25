import { useState, useEffect } from "react";
import { useLocation } from "react-router-dom";
import Footer from "./components/footer";
import Navbar from "./components/navbar";
import { tel, email, adress, name, corpName } from "../assets/constants";
import "./styles/request.scss";
import "./styles/main.scss";
import "./styles/legals.scss";

const MAX_LENGTH = 500;

export default function Legal() {
  const { hash } = useLocation();

  useEffect(() => {
    if (hash) {
      // Entfernt das '#' und sucht das Element mit der passenden ID
      const element = document.getElementById(hash.replace("#", ""));
      if (element) {
        // Scrollt sanft oder direkt ('auto') zum Abschnitt
        element.scrollIntoView({ behavior: "smooth" });
      }
    }
  }, [hash]);

  return (
    <div className="body-div legal-content">
      <Navbar />
      <div className="legal-div">
        <h2 id="imprint">Impressum</h2>
      </div>
      <div className="legal-div">
        <h2 id="data-declaration">Datenschutz</h2>
      </div>
      <div className="legal-div">
        <h2 id="terms-conditions">Allgemeine Geschäftsbedingungen (AGB)</h2>
        <h3>Wichtiger Hinweis</h3>
        {/*====================================
        DISCLAIMER
        =======================================*/}
        <ul>
          <p>
            Die giulianaCARE Telefonbetreuung ist ein{" "}
            <b>Unterstützungsservice</b>. Sie ersetzt Folgendes <b>nicht</b>;
          </p>
          <li>Notruf 112</li>
          <li>einen Hausnotrufdienst gemäß § 78 SGB XI </li>
          <li>ärtzliche oder pflegerische Betreuung</li>
          <li>persönliche Vor-Ort-Betreuung</li>
        </ul>
        {/*========================================
        AGB 
        ===========================================*/}
        <ul>
          <div className="paragraph">
            <h3> § 1 Geltungsbereich und Anbieter</h3>
            <li>
              (1) Diese Allgemeinen Geschäftsbedingungen (AGB) gelten für alle
              Verträge zwischen:
              <div className="impress-div"></div>– nachfolgend "Anbieter" oder
              "HELFI-RUF" genannt – und den Kunden über die Nutzung der
              giulianaCARE Telefonbetreuung.
            </li>
            <li>
              (2) Diese AGB gelten sowohl gegenüber Verbrauchern (§ 13 BGB) als
              auch gegenüber Unternehmern (§ 14 BGB). Abweichende Bedingungen
              des Kunden werden nicht anerkannt, es sei denn, der Anbieter
              stimmt ihrer Geltung ausdrücklich schriftlich zu.
            </li>
            <li>
              (3) Verbraucher im Sinne dieser AGB ist jede natürliche Person,
              die ein Rechtsgeschäft zu Zwecken abschließt, die überwiegend
              weder ihrer gewerblichen noch ihrer selbständigen beruflichen
              Tätigkeit zugerechnet werden können.
            </li>
          </div>

          <div className="paragraph">
            <h3>§ 2 Vertragsgegenstand und Leistungsbeschreibung </h3>
            <li>
              (1) Die giulianaCARE Telefonbetreuung bietet einen automatisierten
              Sicherheitsanruf-Service für Seniorinnen und Senioren an. Der
              Service umfasst je nach gewähltem Tarif:
              <ul className="sub-list">
                <li>1 - 3 automatische Betreuungsanrufe</li>
                <li>KI - gestützte Telefongespräche</li>
                <li>
                  Individuelle Abfragemöglichkeiten (z.B.
                  Medikamentenerinnerung, Flüssigkeitszufuhr etc.)
                </li>
                <li>Benachrichtigungssystem per SMS und E-Mail</li>
              </ul>
            </li>
            <li>
              (2) Der Service ist <b>ausdrücklich kein</b>:
              <ul className="sub-list">
                <li>Notruf oder Notrufersatz (z.B. Notruf 112)</li>
                <li>Hausnotrufdienst im Sinne des § 78 SGB XI</li>
                <li>Medizinischer Dienst oder ärztliche Beratung</li>
                <li>Pflegedienst oder pflegerische Leistung</li>
                <li>System zur garantierten Lebensrettung</li>
              </ul>
            </li>
            <li>
              (3) Der Anbieter bemüht sich um eine zuverlässige
              Leistungserbringung, kann jedoch aufgrund technischer
              Gegebenheiten (Netzausfälle, Serverprobleme, Störungen bei
              Drittanbietern) keine ununterbrochene Verfügbarkeit garantieren.
            </li>
          </div>

          <div className="paragraph">
            <h3>§ 3 Vertragsschluss und Registrierung </h3>
            <li>
              (1) Die Darstellung der Leistungen auf der Website stellt kein
              verbindliches Angebot, sondern eine Aufforderung zur Abgabe eines
              Angebots dar.
            </li>
            <li>
              (2) Durch Absenden der Registrierung gibt der Kunde ein
              verbindliches Angebot zum Abschluss eines Nutzungsvertrages ab.
              Der Vertrag kommt durch die Bestätigung des Anbieters per E-Mail
              zustande.
            </li>
            <li>
              (3) Der Vertragspartner kann sein:
              <ul className="sub-list">
                <li>Der Senior/ die Seniorin selbst, oder</li>
                <li>
                  Ein Angehöriger oder Bevollmächtigter des Seniors/ der
                  Seniorin
                </li>
              </ul>
            </li>
            <li>
              (4) Der Vertragspartner versichert, dass alle angegebenen Daten
              (insbesondere Telefonnummern, Kontaktpersonen, Medikamente)
              korrekt und vollständig sind.
            </li>
          </div>
          <div className="paragraph">
            <li>
              <h3>§ 4 Kostenlose Testphase </h3>
              (1) Neukunden erhalten eine kostenlose Testphase von{" "}
              <b>14 Tagen</b> ab Aktivierung des Accounts.
            </li>
            <li>
              (2) Zusätzlich besteht die Möglichkeit, vor der Registrierung
              einen kostenlosen Testanruf anzufordern.
            </li>
            <li>
              (3) Die Testphase geht automatisch in ein kostenpflichtiges
              Abonnement über, wenn der Kunde nicht vor Ablauf der Testphase
              kündigt. Der Kunde wird rechtzeitig per E-Mail an das Ende der
              Testphase erinnert.
            </li>
            <li>
              (4) Während der Testphase kann der Vertrag jederzeit ohne Angabe
              von Gründen und ohne Kosten gekündigt werden.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 5 Preise und Zahlungsbedingungen </h3>
            <li>
              (1) Es gelten die zum Zeitpunkt des Vertragsschlusses auf der
              Website ausgewiesenen Preise. Alle Preise verstehen sich inklusive
              der gesetzlichen Mehrwertsteuer.
            </li>
            <li>
              (2) Die aktuellen Tarife sind:
              <ul className="sub-list">
                <li>Sicherheit - 1 Anruf pro Tag - €</li>
                <li>Gut betreut - 2 Anrufe pro Tag - €</li>
                <li>Rundum sorglos - 3 Anrufe pro Tag - €</li>
              </ul>
            </li>
            <li>
              (3) Für jeden Anruf ist eine Zusatzinformation (z.B
              Medikamentenerinnerung) kostenlos enthalten. Weitere Angaben und
              Abfragen können auf Wunsch hinzugebucht werden.
            </li>
            <li>
              (4) Die Zahlung erfolgt monatlich im Voraus. Der Rechnungsbetrag
              wird zu Beginn jedes Abrechnungszeitraums fällig.
            </li>
            <li>
              (5) Folgende Zahlungsmethoden werden akzeptiert:
              <ul className="sub-list">
                <li>Alle über Stripe verfügbaren Zahlungsmittel</li>
              </ul>
            </li>
            <li>
              (6) Bei Zahlungsverzug ist der Anbieter berechtigt, nach
              zweifacher erfolgloser Mahnung den Zugang zum Service zu sperren.
              Die Pflicht zur Zahlung der ausstehenden Beträge bleibt unberührt.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 6 Vertragslaufzeit und Kündigung </h3>
            <li>
              (1) Der Vertrag wird auf unbestimmte Zeit geschlossen und kann
              jederzeit mit einer Frist von 14 Tagen zum Monatsende gekündigt
              werden.
            </li>
            <li>
              (2) Die Kündigung kann erfolgen:
              <ul className="sub-list">
                <li>Schriftlich per Post</li>
                <li>Per E-Mail an info@giuliana-care.de</li>
                <li>Über den Kündigungs-Button im Dashboard</li>
              </ul>
            </li>
            <li>
              (3) Das Recht zur außerordentlichen Kündigung aus wichtigem Grund
              bleibt unberührt.
            </li>
            <li>
              (4) Mit Wirksamwerden der Kündigung werden die automatischen
              Anrufe eingestellt. Bereits gezahlte Beträge für den laufenden
              Abrechnungszeitraum werden nicht erstattet.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 7 Widerrufsrecht (Verbraucher) </h3>
            <li>
              (1) Verbrauchern steht ein gesetzliches Widerrufsrecht zu. Die
              Widerrufsbelehrung und das Muster-Widerrufsformular finden Sie am
              Ende dieser AGB.
            </li>
            <li>
              (2) Mit der Registrierung erklärt sich der Kunde einverstanden,
              dass der Anbieter vor Ablauf der Widerrufsfrist mit der Ausführung
              der Dienstleistung beginnt.
            </li>
            <li>
              (3) Bei Widerruf nach Beginn der Leistungserbringung hat der Kunde
              einen angemessenen Betrag für die bis zum Widerruf erbrachten
              Leistungen zu zahlen (anteiliger Wertersatz).
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 8 Haftungsbeschränkung </h3>
            <li>
              (1) Leistungscharakter: giulianaCARE Telefonbetreuung ist ein
              technischer Unterstützungs- und Erinnerungsservice. Der Service
              ersetzt nicht den Notruf 112, Hausnotrufdienste, ärztliche
              Betreuung, pflegerische Versorgung oder die persönliche
              Anwesenheit von Angehörigen.
            </li>
            <li>
              (2) Haftungsausschluss: Der Anbieter haftet nicht für Schäden, die
              entstehen durch:
              <ul className="sub-list">
                <li>
                  Technische Störungen (Server-, Netz- oder Stromausfälle)
                </li>
                <li>
                  Störungen bei Drittanbietern (Telekommunikationsanbieter,
                  Cloud-Dienste, Hosting-Anbieter)
                </li>
                <li>
                  Nicht-Erreichbarkeit des Seniors (Telefon ausgeschaltet, nicht
                  abgenommen, besetzt)
                </li>
                <li>
                  Fehlerhafte Einschätzungen durch die KI-gestützte
                  Gesprächsführung
                </li>
                <li>
                  Verpasste, verspätete oder nicht zugestellte Alarme an
                  Kontaktpersonen
                </li>
                <li>
                  Nicht erfolgte oder vergessene Medikamenteneinnahme trotz
                  Erinnerung
                </li>
                <li>
                  Nicht erfolgte oder vergessene Befolgung der angegebenen
                  Zusatzinformationen oder Abfragen{" "}
                </li>
                <li>Falsche oder unvollständige Angaben des Kunden</li>
              </ul>
            </li>
            <li>
              (3) Beschränkung auf Vorsatz und grobe Fahrlässigkeit: Der
              Anbieter haftet nur für Schäden, die auf vorsätzlichem oder grob
              fahrlässigem Verhalten des Anbieters, seiner gesetzlichen
              Vertreter oder Erfüllungsgehilfen beruhen.
            </li>
            <li>
              (4) Wesentliche Vertragspflichten: Bei Verletzung wesentlicher
              Vertragspflichten (Kardinalpflichten) haftet der Anbieter auch bei
              einfacher Fahrlässigkeit, jedoch beschränkt auf den
              vertragstypischen, vorhersehbaren Schaden.
            </li>
            <li>
              (5) Haftungshöchstgrenze: Die Haftung des Anbieters ist – soweit
              gesetzlich zulässig – auf den jährlichen Vertragswert des
              betroffenen Kunden beschränkt.
            </li>
            <li>
              (6) Ausschluss von Folgeschäden: Der Anbieter haftet nicht für
              indirekte Schäden, entgangenen Gewinn, Vermögensschäden oder
              Folgeschäden jeder Art.
            </li>
            <li>
              (7) Unberührte Haftung: Die vorstehenden Haftungsbeschränkungen
              gelten nicht für:
              <ul className="sub-list">
                <li>
                  Schäden aus der Verletzung von Leben, Körper oder Gesundheit,
                  die auf einer fahrlässigen Pflichtverletzung des Anbieters
                  beruhen
                </li>
                <li>Haftung nach dem Produkthaftungsgesetz</li>
                <li>Ausdrücklich übernommene Garantien</li>
              </ul>
            </li>
            <li>
              (8) Kausalität: Der Kunde trägt die Beweislast für die Kausalität
              zwischen einer Pflichtverletzung des Anbieters und dem
              eingetretenen Schaden.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 9 Pflichten des Kunden</h3>
            <li>
              (1) Der Kunde ist verpflichtet:{" "}
              <ul className="sub-list">
                <li>
                  Korrekte und vollständige Angaben bei der Registrierung zu
                  machen
                </li>
                <li>
                  Änderungen seiner Daten unverzüglich im Portal zu
                  aktualisieren
                </li>
                <li>
                  Mindestens eine erreichbare Kontaktperson zu hinterlegen
                </li>
                <li>
                  Dafür zu sorgen, dass das Telefon des Seniors zu den
                  Anrufzeiten eingeschaltet und erreichbar ist
                </li>
                <li>
                  Medikamenteninformationen aktuell zu halten, falls diese
                  Funktion genutzt wird
                </li>
              </ul>
            </li>
            <li>
              (2) Der Kunde wird ausdrücklich darauf hingewiesen, dass in
              medizinischen Notfällen immer der Notruf 112 zu wählen ist.
            </li>
            <li>
              (3) Der Kunde stellt sicher, dass der Senior/ die Seniorin und
              alle Kontaktpersonen über die Nutzung des Services informiert sind
              und eingewilligt haben.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 10 Pflichten des Anbieters</h3>
            <li>
              (1) Der Anbieter bemüht sich, die vereinbarten Anrufe zu den
              festgelegten Zeiten durchzuführen.
            </li>
            <li>
              (2) Bei Nicht-Erreichbarkeit des Seniors unternimmt das System bis
              zu 2 Anrufversuche mit zeitlichem Abstand.
            </li>
            <li>
              (3) Nach Ausschöpfung aller Anrufversuche werden die hinterlegten
              Kontaktpersonen per SMS und/oder E-Mail benachrichtigt ("Best
              Effort" – keine Garantie der Zustellung).
            </li>
            <li>
              (4) Der Anbieter stellt das Online-Portal zur Verwaltung des
              Accounts zur Verfügung.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 11 Leistungsänderungen und Preisanpassungen</h3>
            <li>
              (1) Der Anbieter behält sich vor, den Leistungsumfang zu erweitern
              oder zu verbessern.
            </li>
            <li>
              (2) Preisänderungen werden dem Kunden mindestens 4 Wochen vor
              Inkrafttreten per E-Mail mitgeteilt.
            </li>
            <li>
              (3) Bei Preiserhöhungen hat der Kunde ein Sonderkündigungsrecht
              zum Zeitpunkt des Inkrafttretens der neuen Preise.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 12 Datenschutz</h3>
            <li>
              (1) Der Anbieter verarbeitet personenbezogene Daten gemäß der
              EU-Datenschutzgrundverordnung (DSGVO) und dem
              Bundesdatenschutzgesetz (BDSG).
            </li>
            <li>
              (2) Details zur Datenverarbeitung finden Sie in unserer{" "}
              <a href="#data-declaration" className="colored-link">
                Datenschutzerklärung
              </a>
              .
            </li>
            <li>
              (3) Insbesondere werden Anruf-Transkripte zur Qualitätssicherung
              gespeichert. Transkripte aus den Anrufen eines laufenden
              Abonnements werden nach 90 Tagen gelöscht, Transkripte kostenloser
              Testanrufe nach 24 Monaten ab dem letzten Kontakt. Die im
              Einzelnen geltenden Fristen finden Sie in Abschnitt 5.3 der
              Datenschutzerklärung.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 13 Gewährleistung</h3>
            <li>
              (1) Der Anbieter gewährleistet, dass der Service im Wesentlichen
              der Leistungsbeschreibung entspricht.
            </li>
            <li>
              (2) Der Kunde ist verpflichtet, Mängel unverzüglich nach
              Feststellung zu melden.
            </li>
            <li>
              (3) Bei berechtigten Mängelrügen wird der Anbieter nach seiner
              Wahl den Mangel beseitigen oder eine Ersatzleistung erbringen.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 14 Höhere Gewalt</h3>
            <li>
              (1) Der Anbieter haftet nicht für Leistungsstörungen, die auf
              höhere Gewalt zurückzuführen sind.
            </li>
            <li>
              (2) Als höhere Gewalt gelten insbesondere:
              <ul className="sub-list">
                <li>Naturkatastrophen</li>
                <li>Krieg, Terroranschläge, Aufruhr</li>
                <li>Epidemien und Pandemien</li>
                <li>Großflächige Stromausfälle</li>
                <li>Ausfall von Telekommunikationsnetzen</li>
                <li>Behördliche Anordnungen</li>
                <li>Cyberangriffe auf kritische Infrastruktur</li>
              </ul>
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 15 Sperrung und Kündigung durch Anbieter</h3>
            <li>
              (1) Der Anbieter ist berechtigt, den Zugang zum Service
              vorübergehend zu sperren bei:
              <ul className="sub-list">
                <li>Zahlungsverzug von mehr als 14 Tagen trotz Mahnung</li>
                <li>Missbrauch des Services</li>
                <li>Verstoß gegen diese AGB</li>
              </ul>
            </li>
            <li>
              (2) Der Anbieter kann den Vertrag aus wichtigem Grund
              außerordentlich kündigen, insbesondere bei wiederholten oder
              schwerwiegenden Vertragsverletzungen durch den Kunden.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 16 Vertragsübertragung</h3>
            <li>
              (1) Der Kunde kann seine Rechte und Pflichten aus diesem Vertrag
              nur mit vorheriger schriftlicher Zustimmung des Anbieters auf
              Dritte übertragen.
            </li>
            <li>
              (2) Der Anbieter ist berechtigt, Rechte und Pflichten aus diesem
              Vertrag auf verbundene Unternehmen oder Rechtsnachfolger zu
              übertragen.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 17 Schlussbestimmungen und Salvatorische Klausel</h3>
            <li>
              (1) Es gilt das Recht der Bundesrepublik Deutschland unter
              Ausschluss des UN-Kaufrechts.
            </li>
            <li>
              (2) Sollten einzelne Bestimmungen dieser AGB unwirksam sein oder
              werden, bleibt die Wirksamkeit der übrigen Bestimmungen unberührt.
            </li>
            <li>
              (3) Änderungen oder Ergänzungen dieser AGB bedürfen der
              Schriftform. Dies gilt auch für die Abbedingung dieses
              Schriftformerfordernisses.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 18 Gerichtsstand (B2B)</h3>
            <li>
              (1) Für Unternehmer ist der Gerichtsstand für alle Streitigkeiten
              aus oder im Zusammenhang mit diesem Vertrag der Sitz des
              Anbieters.
            </li>
            <li>
              (2) Für Verbraucher gelten die gesetzlichen Regelungen zum
              Gerichtsstand.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 19 Streitbeilegung</h3>
            <li>
              Der Anbieter ist zur Teilnahme an einem Streitbeilegungsverfahren
              vor einer Verbraucherschlichtungsstelle weder bereit noch
              verpflichtet.
            </li>
          </div>
          <div className="paragraph">
            <h3>§ 20 Kontakt und Beschwerden</h3>
            <li>
              Bei Fragen, Beschwerden oder Anregungen erreichen Sie uns unter:
              <ul className="sub-list">
                <li>Telefon: {tel}</li>
                <li>E-Mail-Adresse: {email}</li>
              </ul>
            </li>
          </div>
          <div className="paragraph">
            <h3>Widerrufsrecht</h3>
            <li>
              Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen
              diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn
              Tage ab dem Tag des Vertragsschlusses.
            </li>
            <li>
              Um Ihr Widerrufsrecht auszuüben, müssen Sie uns mittels einer
              eindeutigen Erklärung (z.B. ein mit der Post versandter Brief oder
              E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen,
              informieren. Sie können dafür das beigefügte
              Muster-Widerrufsformular verwenden, das jedoch nicht
              vorgeschrieben ist. Zur Wahrung der Widerrufsfrist reicht es aus,
              dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor
              Ablauf der Widerrufsfrist absenden.
              <ul className="sub-list">
                <li>
                  Postalisch: {corpName}, {name}, {adress}
                </li>
                <li>Per E-Mail: {email}</li>
              </ul>
            </li>
            <h3>Folgen des Widerrufs</h3>
            <li>
              Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle
              Zahlungen, die wir von Ihnen erhalten haben, unverzüglich und
              spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem
              die Mitteilung über Ihren Widerruf dieses Vertrags bei uns
              eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe
              Zahlungsmittel, das Sie bei der ursprünglichen Transaktion
              eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas
              anderes vereinbart; in keinem Fall werden Ihnen wegen dieser
              Rückzahlung Entgelte berechnet. Haben Sie verlangt, dass die
              Dienstleistung während der Widerrufsfrist beginnen soll, so haben
              Sie uns einen angemessenen Betrag zu zahlen, der dem Anteil der
              bis zu dem Zeitpunkt, zu dem Sie uns von der Ausübung des
              Widerrufsrechts hinsichtlich dieses Vertrags unterrichten, bereits
              erbrachten Dienstleistungen im Vergleich zum Gesamtumfang der im
              Vertrag vorgesehenen Dienstleistungen entspricht.
            </li>
            <div className="reject-form">
              An:
              <ul>
                <li>{corpName}</li>
                <li>{name}</li>
                <li>{adress}</li>
                <li>{email}</li>
              </ul>
              <br />
              <br />
              <p>
                Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*)
                abgeschlossenen Vertrag über die Erbringung der folgenden
                Dienstleistung: <br /> <br />{" "}
                <b>giulianaCARE Telefonbetreuung</b> <br />
                <br />
                Bestellt am (*) / erhalten am (*): <br />
                <br />
                Name des/der Verbraucher(s): <br />
                <br />
                Anschrift des/der Verbraucher(s): <br />
                <br />
                Datum: <br />
                <br />
                Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf
                Papier): <br />
                <br />
                <span className="small-font">
                  (*) Unzutreffendes streichen.
                </span>
              </p>
            </div>
          </div>
        </ul>
      </div>
      <Footer />
    </div>
  );
}
