import Navbar from "./components/navbar";
import Footer from "./components/footer";
import "./styles/imprint.scss";
import {
  tel,
  email,
  adress,
  name,
  corpName,
  price1,
  price2,
  price3,
} from "../assets/constants";

export function Imprint() {
  return (
    <div className="body-div imprint-div">
      <Navbar />
      <div className="imprint-content">
        <h2>Impressum</h2>

        <section className="imprint-section">
          <h3>Angaben gemäß § 5 DDG</h3>
          <p>
            {name}
            <br />
            {corpName}
            <br />
            {adress} <br />
          </p>
        </section>

        <section className="imprint-section">
          <h3>Kontakt</h3>
          <p>
            Telefon: {tel}
            <br />
            E-Mail: {email}
          </p>
        </section>

        <section className="imprint-section">
          <h3>Umsatzsteuer-ID</h3>
          <p>
            Gemäß § 19 UStG wird keine Umsatzsteuer berechnet und ausgewiesen
            (Kleinunternehmerregelung).
          </p>
          {/* Falls du doch eine USt-IdNr. beim Finanzamt beantragt hast:
          <p>
            Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:<br />
            [DE123456789]
          </p> 
          */}
        </section>

        <section className="imprint-section">
          <h3>Redaktionell verantwortlich</h3>
          <p>
            [Vorname Nachname]
            <br />
            [Straße und Hausnummer]
            <br />
            [PLZ und Ort]
          </p>
        </section>

        <section className="imprint-section">
          <h3>EU-Streitschlichtung</h3>
          <p>
            Die Europäische Kommission stellt eine Plattform zur
            Online-Streitbeilegung (OS) bereit:{" "}
            <a
              href="https://ec.europa.eu/consumers/odr/"
              target="_blank"
              rel="noopener noreferrer"
            >
              https://ec.europa.eu/consumers/odr/
            </a>
            .<br />
            Unsere E-Mail-Adresse finden Sie oben im Impressum.
          </p>
        </section>

        <section className="imprint-section">
          <h3>Verbraucher­streit­beilegung / Universalschlichtungs­stelle</h3>
          <p>
            Wir sind nicht bereit oder verpflichtet, an
            Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle
            teilzunehmen.
          </p>
        </section>
      </div>
      <Footer />
    </div>
  );
}
