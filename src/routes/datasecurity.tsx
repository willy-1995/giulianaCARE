import Navbar from "./components/navbar";
import Footer from "./components/footer";
import "./styles/legals.scss";
import {
  tel,
  email,
  adress,
  name,
  corpName,
  price1,
  price2,
  price3,
  country,
} from "../assets/constants";

export function Datasecurity() {
  return (
    <div className="body-div legal-content">
      <Navbar />

      <div className="legal-div">
        <h2 id="data-declaration">Datenschutzerklärung</h2>

        <div className="paragraph">
          <p>
            <strong>Stand: September 2026</strong>
          </p>

          <p>
            Der Schutz Ihrer personenbezogenen Daten ist uns wichtig. Diese
            Datenschutzerklärung informiert Sie darüber, welche
            personenbezogenen Daten bei der Nutzung von {corpName} verarbeitet
            werden, zu welchen Zwecken dies geschieht und welche Rechte Ihnen
            nach der Datenschutz-Grundverordnung (DSGVO) zustehen.
          </p>

          <p>
            {corpName} ist eine technische Anwendung zur Unterstützung der
            Betreuung von Personen. Im Rahmen der Nutzung können insbesondere
            Gesundheitsdaten und andere besonders schützenswerte
            personenbezogene Daten verarbeitet werden.
          </p>
        </div>

        <div className="paragraph">
          <h3>1. Verantwortlicher</h3>
          <p>
            Verantwortlicher für die Verarbeitung personenbezogener Daten im
            Rahmen dieser Website und der Nutzung von {corpName} ist:
          </p>
          <br />
          <ul>
            <li>{corpName}</li>
            <li>{name}</li>
            <li>{adress}</li>
            <li>{country}</li>
            <li>E-Mail: {email}</li>
            <li>Telefon: {tel}</li>
          </ul>
          <br />
          <p>
            Verantwortlicher im Sinne der DSGVO ist die natürliche oder
            juristische Person, die über die Zwecke und Mittel der Verarbeitung
            personenbezogener Daten entscheidet.
          </p>
        </div>

        <div className="paragraph">
          <h3>2. Welche Daten werden verarbeitet?</h3>
          <p>
            Im Rahmen von {corpName} können abhängig von den tatsächlich
            verwendeten Funktionen insbesondere folgende personenbezogene Daten
            verarbeitet werden:
          </p>
          <h4>Accountdaten</h4>
          <ul>
            <li>Land</li>
            <li>Postleitzahl</li>
            <li>E-Mail-Adresse</li>
            <li>Passwort bzw. hierfür gespeicherte technische Informationen</li>
          </ul>

          <h4>Daten der betreuten Person</h4>

          <ul>
            <li>Vor- und Nachname</li>
            <li>Telefonnummern</li>
            <li>Adresse</li>
            <li>Geburtsdatum</li>
            <li>Medikationsangaben</li>
            <li>sonstige vom Nutzer eingegebene Informationen</li>
            <li>Gesundheitsdaten und Gesundheitsmesswerte</li>
          </ul>

          <h4>Daten der Notfallkontakte</h4>

          <ul>
            <li>Vor- und Nachname</li>
            <li>E-Mail-Adresse</li>
            <li>Telefonnummer</li>
          </ul>

          <h4>Technische und anrufbezogene Daten</h4>

          <ul>
            <li>Telefonnummer des angerufenen Anschlusses</li>
            <li>Zeitpunkt und Status eines Anrufs</li>
            <li>Anrufversuche</li>
            <li>technische Kommunikationsdaten</li>
            <li>Gesprächszusammenfassungen</li>
            <li>im Gespräch mitgeteilte Beschwerden bzw. Notfallgründe</li>
          </ul>
        </div>

        <div className="paragraph">
          <h3>3. Erstellung und Verwaltung eines Benutzerkontos</h3>
          <p>
            Für die Nutzung bestimmter Funktionen von {corpName} ist ein
            Benutzerkonto erforderlich.
          </p>
          <p>
            Bei der Registrierung und Verwaltung des Benutzerkontos können
            insbesondere folgende Daten verarbeitet werden:
          </p>

          <ul className="data-ul">
            <li>Land</li>
            <li>Postleitzahl</li>
            <li>E-Mail-Adresse</li>
            <li>Passwort bzw. die hierfür erforderlichen technischen Daten</li>
            <li>Zahlungsdaten</li>
          </ul>

          <p>
            Die Verarbeitung erfolgt zur Erstellung, Verwaltung und
            Authentifizierung des Benutzerkontos sowie zur Bereitstellung der
            von Ihnen angeforderten Funktionen.
          </p>
          <br />
          <p>
            Rechtsgrundlage ist grundsätzlich Art. 6 Abs. 1 lit. b DSGVO, soweit
            die Verarbeitung zur Durchführung des Nutzungsverhältnisses
            erforderlich ist.
          </p>
        </div>

        <div className="paragraph">
          <h3>4. Daten der betreuten Person</h3>

          <p>
            {corpName} ermöglicht es dem Nutzer, Informationen über eine
            betreute Person zu hinterlegen, damit die vorgesehenen Betreuungs-
            und Anruffunktionen durchgeführt werden können.
          </p>

          <p>Hierzu können insbesondere verarbeitet werden:</p>

          <ul className="data-ul">
            <li>Name</li>
            <li>Telefonnummer</li>
            <li>Adresse</li>
            <li>Geburtsdatum</li>
            <li>Medikationsangaben</li>
            <li>sonstige vom Nutzer eingegebene Informationen</li>
            <li>Gesundheitsdaten und Messwerte</li>
          </ul>

          <p>
            Bei den Daten der betreuten Person kann es sich um personenbezogene
            Daten einer anderen Person als des registrierten Nutzers handeln.
            Der Nutzer ist dafür verantwortlich, dass er zur Erhebung und
            Übermittlung dieser Daten berechtigt ist und die betroffene Person
            im erforderlichen Umfang über die Verarbeitung informiert wird.
          </p>
        </div>

        <div className="paragraph">
          <h3>5. Gesundheitsdaten</h3>

          <p>
            Im Rahmen von {corpName} können Gesundheitsdaten verarbeitet werden.
            Hierzu können beispielsweise gehören:
          </p>

          <ul className="data-ul">
            <li>Puls bzw. Herzfrequenz</li>
            <li>Medikationsangaben</li>
            <li>Angaben zu Beschwerden</li>
            <li>sonstige gesundheitliche Informationen</li>
            <li>weitere Gesundheitsmesswerte</li>
          </ul>

          <p>
            Gesundheitsdaten gehören zu den besonderen Kategorien
            personenbezogener Daten im Sinne von Art. 9 DSGVO und unterliegen
            einem besonderen Schutz.
          </p>

          <p>
            Die Verarbeitung von Gesundheitsdaten erfolgt nur, soweit hierfür
            eine entsprechende Rechtsgrundlage nach Art. 9 DSGVO besteht. Soweit
            die Verarbeitung auf einer ausdrücklichen Einwilligung beruht, ist
            insbesondere Art. 9 Abs. 2 lit. a DSGVO in Verbindung mit Art. 6
            Abs. 1 lit. a DSGVO einschlägig.
          </p>

          <p>
            Welche konkrete Rechtsgrundlage im Einzelfall Anwendung findet,
            hängt vom jeweiligen Zweck der Verarbeitung und vom konkreten
            Nutzungsverhältnis ab.
          </p>
        </div>

        <div className="paragraph">
          <h3>6. Automatisierte Telefonanrufe</h3>

          <p>
            {corpName} kann automatisierte Telefonanrufe durchführen, um
            beispielsweise den Zustand einer betreuten Person zu erfragen oder
            an hinterlegte Medikationszeiten zu erinnern.
          </p>

          <p>
            Für die Durchführung eines solchen Anrufs können insbesondere
            folgende Informationen verwendet werden:
          </p>

          <ul className="data-ul">
            <li>Name der betreuten Person</li>
            <li>Telefonnummer</li>
            <li>hinterlegte Medikationsinformationen</li>
            <li>vorgesehener Anruf bzw. Anrufzeitpunkt</li>
            <li>technische Informationen zum Anruf</li>
          </ul>

          <p>
            Die Telefonnummer wird an den für die Telefoniefunktion eingesetzten
            Dienst übermittelt, damit der Telefonanruf technisch durchgeführt
            werden kann.
          </p>
        </div>

        <div className="paragraph">
          <h3>7. Verarbeitung von Sprachdaten und Gesprächsinhalten</h3>

          <p>
            Bei einem automatisierten Telefonat werden die Äußerungen der
            angerufenen Person technisch verarbeitet, damit der Sprachassistent
            die Äußerungen verstehen und darauf reagieren kann.
          </p>

          <p>
            Gesprächsinhalte können personenbezogene Daten und insbesondere
            Gesundheitsdaten enthalten, wenn die betreute Person beispielsweise
            Beschwerden, Schmerzen, gesundheitliche Veränderungen oder andere
            medizinische Informationen mitteilt.
          </p>

          <p>
            Im Rahmen der Anwendung wird aus einem Gespräch außerdem eine
            Gesprächszusammenfassung verarbeitet. Diese kann ebenfalls
            personenbezogene oder gesundheitsbezogene Informationen enthalten.
          </p>

          <p>
            Die Verarbeitung erfolgt ausschließlich zu den für die
            Telefonassistenz und die vorgesehenen Betreuungsfunktionen
            erforderlichen Zwecken.
          </p>
        </div>

        <div className="paragraph">
          <h3>8. Vapi</h3>

          <p>
            Für die technische Durchführung und Orchestrierung der KI-gestützten
            Telefonassistenz wird Vapi eingesetzt.
          </p>

          <p>
            Im Rahmen eines Telefonats können insbesondere folgende Daten an
            Vapi übermittelt bzw. durch Vapi verarbeitet werden:
          </p>

          <ul className="data-ul">
            <li>Telefonnummer der betreuten Person</li>
            <li>Name der betreuten Person</li>
            <li>für den jeweiligen Anruf erforderliche Konfigurationsdaten</li>
            <li>gegebenenfalls Medikationsinformationen</li>
            <li>Sprach- und Gesprächsinhalte</li>
            <li>gegebenenfalls Beschwerden und Gesundheitsinformationen</li>
            <li>technische Informationen zum Telefonat</li>
          </ul>

          <p>
            Vapi verarbeitet nach eigenen Angaben personenbezogene Daten wie
            Namen, E-Mail-Adressen, Telefonnummern und Adressen. Vapi gibt
            außerdem an, geeignete technische und organisatorische
            Sicherheitsmaßnahmen einzusetzen und bei internationalen
            Datenübermittlungen geeignete Garantien wie Standardvertragsklauseln
            zu verwenden.
          </p>

          <p>
            Die konkrete Verarbeitung und Speicherdauer hängt von der
            eingesetzten Vapi-Konfiguration und den jeweils verwendeten
            Unterauftragnehmern ab.
          </p>
        </div>

        <div className="paragraph">
          <h3>9. Azure OpenAI</h3>

          <p>
            Für die Verarbeitung der Sprache und die Generierung der Antworten
            des Telefonassistenten wird Azure OpenAI von Microsoft eingesetzt.
          </p>

          <p>
            Dabei können Informationen aus dem Telefonat sowie Informationen,
            die für die Durchführung des jeweiligen Telefonats erforderlich
            sind, an Azure OpenAI übermittelt werden.
          </p>

          <p>Hierzu können insbesondere gehören:</p>

          <ul className="data-ul">
            <li>Gesprächsinhalte</li>
            <li>Fragen und Antworten des Telefonats</li>
            <li>gegebenenfalls Gesundheitsinformationen</li>
            <li>für den Anruf erforderliche Kontextinformationen</li>
          </ul>

          <p>
            Die verwendete Azure-OpenAI-Ressource ist nach der derzeitigen
            Konfiguration in der Region Frankfurt bzw. Deutschland
            bereitgestellt.
          </p>

          <p>
            Bei Azure-Deployments ist allerdings zwischen regionalen, DataZone-
            und Global-Deployments zu unterscheiden. Bei einem
            regionalen/geografisch gebundenen Deployment werden Prompts und
            Antworten innerhalb der vom Kunden angegebenen Geografie
            verarbeitet. Bei Global-Deployments kann die Verarbeitung dagegen
            auch in anderen geografischen Regionen erfolgen.
          </p>

          <p>
            Die konkrete Datenverarbeitung richtet sich daher nach dem
            tatsächlich verwendeten Azure-Deployment.
          </p>

          <p>
            Microsoft gibt an, dass Prompts und Antworten von Azure OpenAI nicht
            zum Training, Retraining oder zur Verbesserung der zugrunde
            liegenden Foundation Models verwendet werden.
          </p>
        </div>

        <div className="paragraph">
          <h3>10. Twilio</h3>

          <p>
            Für Telefonie und den Versand von SMS-Nachrichten wird Twilio
            eingesetzt.
          </p>

          <p>
            Im Rahmen der Telefoniefunktion kann insbesondere die Telefonnummer
            der betreuten Person an Twilio übermittelt werden.
          </p>

          <p>
            Im Rahmen einer Notfallbenachrichtigung kann außerdem die
            Telefonnummer eines hinterlegten Notfallkontakts an Twilio
            übermittelt werden.
          </p>

          <p>
            Twilio kann im Rahmen der Kommunikationsdienste insbesondere
            Telefonnummern, Kommunikationsnutzungsdaten und Inhalte der
            Kommunikation verarbeiten.
          </p>

          <p>
            Nach dem aktuellen Data Protection Addendum von Twilio können
            personenbezogene Daten auch als sensible Daten bzw. besondere
            Kategorien personenbezogener Daten verarbeitet werden, sofern solche
            Inhalte über die Dienste übertragen werden.
          </p>

          <p>
            Für internationale Datenübermittlungen sieht Twilio unter anderem
            Standardvertragsklauseln und weitere geeignete
            Übermittlungsmechanismen vor.
          </p>
        </div>

        <div className="paragraph">
          <h3>11. Notfallfunktion</h3>

          <p>
            Wenn die betreute Person während eines automatisierten Telefonats
            angibt, dass es ihr nicht gut geht und der Benachrichtigung der
            Notfallkontakte zustimmt, kann die Notfallfunktion ausgelöst werden.
          </p>

          <p>
            Dabei wird der vom Telefonassistenten ermittelte Grund bzw. die
            Beschreibung der Beschwerden in der Datenbank protokolliert.
          </p>

          <p>
            Zusätzlich können die hinterlegten Notfallkontakte per E-Mail und,
            sofern die SMS-Funktion aktiviert ist und eine Telefonnummer
            hinterlegt wurde, per SMS informiert werden.
          </p>

          <p>Eine solche Nachricht kann insbesondere enthalten:</p>

          <ul className="data-ul">
            <li>Name der betreuten Person</li>
            <li>Hinweis auf den bestehenden Hilfebedarf</li>
            <li>vom Telefonassistenten ermittelter Grund bzw. Beschwerden</li>
          </ul>

          <p>
            Da diese Informationen Gesundheitsdaten enthalten können, erfolgt
            die Übermittlung nur, soweit hierfür eine entsprechende
            datenschutzrechtliche Rechtsgrundlage besteht.
          </p>
        </div>

        <div className="paragraph">
          <h3>12. Speicherung von Vorfällen und Gesprächszusammenfassungen</h3>

          <p>
            Im Rahmen der Anwendung werden bestimmte Informationen über
            Telefonanrufe und Ereignisse in der Datenbank gespeichert.
          </p>

          <p>Hierzu gehören insbesondere:</p>

          <ul className="data-ul">
            <li>Status des Anrufs</li>
            <li>Anrufversuche</li>
            <li>verwendete Telefonnummer bzw. Nummerntyp</li>
            <li>Anrufart</li>
            <li>Gesprächszusammenfassung</li>
            <li>gemeldete Beschwerden bzw. Notfallgründe</li>
            <li>Zeitpunkt des Ereignisses</li>
          </ul>

          <p>
            Insbesondere die Gesprächszusammenfassung und ein gespeicherter
            Notfallgrund können Gesundheitsdaten enthalten.
          </p>
        </div>

        <div className="paragraph">
          <h3>13. Testanruf</h3>

          <p>
            Auf der Website kann ein Testanruf ausgelöst werden. Hierfür wird
            die vom Nutzer angegebene Telefonnummer an den für die technische
            Durchführung eingesetzten Telefonie- bzw. Sprachdienst übermittelt.
          </p>

          <p>
            Der Testanruf dient ausschließlich dazu, die Funktionsweise der
            Telefonassistenz auszuprobieren.
          </p>

          <p>
            Testanfragen werden technisch begrenzt, um einen missbräuchlichen
            oder übermäßigen Versand von Testanrufen zu verhindern.
          </p>
        </div>

        <div className="paragraph">
          <h3>14. Hosting und Datenbank</h3>

          <p>
            Unsere Website und die zugehörige Datenbank werden bei der IONOS SE
            gehostet.
          </p>

          <ul className="data-ul-no-style">
            <li>IONOS SE</li>
            <li>Elgendorfer Str. 57</li>
            <li>56410 Montabaur</li>
            <li>Deutschland</li>
          </ul>

          <p>
            Im Rahmen des Hostings können technische Daten verarbeitet werden,
            die für den Betrieb und die Sicherheit der Website und der Anwendung
            erforderlich sind.
          </p>

          <p>
            Dazu können insbesondere IP-Adresse, Zeitpunkt des Zugriffs,
            angeforderte Ressourcen und technische Informationen über das
            verwendete Endgerät gehören.
          </p>

          <p>
            Soweit IONOS personenbezogene Daten in unserem Auftrag verarbeitet,
            erfolgt dies auf Grundlage eines Auftragsverarbeitungsvertrags gemäß
            Art. 28 DSGVO.
          </p>
        </div>

        <div className="paragraph">
          <h3>15. Empfänger personenbezogener Daten</h3>

          <p>
            Personenbezogene Daten werden nur an diejenigen Empfänger
            übermittelt, die für den jeweiligen Verarbeitungszweck erforderlich
            sind oder für deren Verarbeitung eine andere Rechtsgrundlage
            besteht.
          </p>

          <p>Hierzu gehören insbesondere:</p>

          <ul className="data-ul">
            <li>IONOS SE – Hosting und technische Infrastruktur</li>
            <li>Vapi – technische Bereitstellung der Sprachassistenz</li>
            <li>Microsoft Azure / Azure OpenAI – KI-Verarbeitung</li>
            <li>Twilio – Telefonie und SMS-Kommunikation</li>
            <li>vom Nutzer hinterlegte Notfallkontakte – im Notfall</li>
          </ul>

          <p>
            Weitere Empfänger können hinzukommen, sofern zusätzliche Dienste
            oder Funktionen in {corpName} integriert werden.
          </p>
        </div>

        <div className="paragraph">
          <h3>16. Übermittlung in Drittländer</h3>

          <p>
            Bei der Nutzung einzelner technischer Dienstleister kann eine
            Verarbeitung personenbezogener Daten außerhalb der Europäischen
            Union bzw. des Europäischen Wirtschaftsraums stattfinden.
          </p>

          <p>
            Soweit eine Übermittlung in ein Drittland erfolgt, erfolgt diese nur
            unter Beachtung der Anforderungen der Art. 44 ff. DSGVO.
          </p>

          <p>
            Als geeignete Garantien können insbesondere
            Angemessenheitsbeschlüsse der Europäischen Kommission,
            Standardvertragsklauseln der Europäischen Kommission oder andere
            gesetzlich zulässige Übermittlungsmechanismen eingesetzt werden.
          </p>

          <p>
            Für Twilio bestehen entsprechende Regelungen im aktuellen Data
            Protection Addendum von Twilio.
          </p>
        </div>

        <div className="paragraph">
          <h3>17. Speicherdauer</h3>

          <p>
            Personenbezogene Daten werden grundsätzlich nur so lange
            gespeichert, wie dies für den jeweiligen Verarbeitungszweck
            erforderlich ist.
          </p>

          <p>
            Die konkrete Speicherdauer richtet sich nach der Art der Daten, dem
            jeweiligen Zweck und gegebenenfalls gesetzlichen
            Aufbewahrungspflichten.
          </p>

          <p>Dies betrifft insbesondere:</p>

          <ul className="data-ul">
            <li>Accountdaten</li>
            <li>Daten der betreuten Personen</li>
            <li>Gesundheitsdaten</li>
            <li>Notfallkontakte</li>
            <li>Anrufdaten</li>
            <li>Gesprächszusammenfassungen</li>
            <li>gespeicherte Notfallgründe</li>
            <li>technische Protokolldaten</li>
          </ul>

          <p>
            Nach Wegfall des jeweiligen Zwecks werden die Daten gelöscht, sofern
            keine gesetzlichen Aufbewahrungspflichten oder andere rechtlich
            zulässige Gründe für eine weitere Speicherung bestehen.
          </p>

          <p>Die Speicherungsfristen betragen:</p>
          <ul className="data-ul">
            <li>Accountdaten</li>
            <li>Daten der betreuten Personen</li>
            <li>Gesundheitsdaten</li>
            <li>Notfallkontakte</li>
            <li>Anrufdaten</li>
            <li>Gesprächszusammenfassungen</li>
            <li>gespeicherte Notfallgründe</li>
            <li>technische Protokolldaten</li>
          </ul>
        </div>

        <div className="paragraph">
          <h3>18. Datensicherheit</h3>

          <p>
            Wir treffen angemessene technische und organisatorische Maßnahmen,
            um personenbezogene Daten vor Verlust, Zerstörung, Manipulation und
            unbefugtem Zugriff zu schützen.
          </p>

          <p>
            Hierzu gehören insbesondere Maßnahmen zur Absicherung der
            Serverkommunikation, der Datenbank und der Zugriffe auf die
            Anwendung.
          </p>

          <p>
            Die Übertragung zwischen dem Nutzer und unserer Website erfolgt über
            eine verschlüsselte SSL-/TLS-Verbindung. Sie erkennen dies an dem
            https:// vor der URL und dem Schloss-Symbol.
          </p>
        </div>

        <div className="paragraph">
          <h3>19. Rechte der betroffenen Personen</h3>

          <p>
            Betroffene Personen haben nach Maßgabe der gesetzlichen
            Voraussetzungen insbesondere folgende Rechte:
          </p>

          <ul className="data-ul">
            <li>Recht auf Auskunft gemäß Art. 15 DSGVO</li>
            <li>Recht auf Berichtigung gemäß Art. 16 DSGVO</li>
            <li>Recht auf Löschung gemäß Art. 17 DSGVO</li>
            <li>
              Recht auf Einschränkung der Verarbeitung gemäß Art. 18 DSGVO
            </li>
            <li>Recht auf Datenübertragbarkeit gemäß Art. 20 DSGVO</li>
            <li>Recht auf Widerspruch gemäß Art. 21 DSGVO</li>
            <li>
              Recht auf Widerruf einer erteilten Einwilligung mit Wirkung für
              die Zukunft
            </li>
          </ul>
        </div>

        <div className="paragraph">
          <h3>20. Widerruf einer Einwilligung</h3>

          <p>
            Soweit personenbezogene Daten auf Grundlage einer Einwilligung
            verarbeitet werden, kann die Einwilligung jederzeit mit Wirkung für
            die Zukunft widerrufen werden.
          </p>

          <p>
            Die Rechtmäßigkeit der aufgrund der Einwilligung bis zum Widerruf
            erfolgten Verarbeitung bleibt vom Widerruf unberührt.
          </p>
        </div>

        <div className="paragraph">
          <h3>21. Beschwerderecht bei einer Aufsichtsbehörde</h3>

          <p>
            Betroffene Personen haben gemäß Art. 77 DSGVO das Recht, sich bei
            einer Datenschutzaufsichtsbehörde zu beschweren, wenn sie der
            Ansicht sind, dass die Verarbeitung ihrer personenbezogenen Daten
            gegen datenschutzrechtliche Vorschriften verstößt.
          </p>

          <p>
            Die Beschwerde kann insbesondere bei der Aufsichtsbehörde des
            gewöhnlichen Aufenthaltsortes, des Arbeitsplatzes oder des Ortes des
            mutmaßlichen Verstoßes eingereicht werden.
          </p>

          <p>
            Die für den Verantwortlichen zuständige Aufsichtsbehörde ist anhand
            des Sitzes des Verantwortlichen zu bestimmen.
          </p>
        </div>

        <div className="paragraph">
          <h3>22. Automatisierte Entscheidungsfindung</h3>

          <p>
            Die in {corpName} eingesetzte KI dient der automatisierten
            Sprachverarbeitung und Kommunikation.
          </p>

          <p>
            Die KI soll keine eigenständigen rechtlichen oder vergleichbar
            erheblichen Entscheidungen über betroffene Personen treffen.
          </p>

          <p>
            Die Auslösung einer Notfallbenachrichtigung erfolgt auf Grundlage
            der vom Nutzer bzw. der betreuten Person im Gespräch mitgeteilten
            Informationen und der hierfür vorgesehenen Funktion der Anwendung.
          </p>
        </div>

        <div className="paragraph">
          <h3>23. Änderungen dieser Datenschutzerklärung</h3>

          <p>
            Wir behalten uns vor, diese Datenschutzerklärung anzupassen, wenn
            sich die technischen Funktionen von {corpName}, die eingesetzten
            Dienstleister oder die rechtlichen Anforderungen ändern.
          </p>

          <p>
            Es gilt jeweils die zum Zeitpunkt des Besuchs auf unserer Website
            veröffentlichte Datenschutzerklärung.
          </p>

          <p>
            <strong>Stand: September 2026</strong>
          </p>
        </div>
      </div>
      <Footer />
    </div>
  );
}
