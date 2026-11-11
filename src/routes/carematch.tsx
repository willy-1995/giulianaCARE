import React, { useState, useEffect } from "react";
import { Link, useNavigate } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import {
  faCheck,
  faThumbsUp,
  faCircleQuestion,
  faHandHoldingHeart,
  faShieldHalved,
  faHandshakeAngle,
  faHeart,
  faUserCheck,
  faUserGroup,
  faFileContract,
  faCertificate,
} from "@fortawesome/free-solid-svg-icons";
import Navbar from "./components/navbar";
import Footer from "./components/footer";
import "./styles/main.scss";
import "./styles/telecare.scss";

function CareMatchLanding() {
  const [currentSlide, setCurrentSlide] = useState(0);
  const [currentImageIndex, setCurrentImageIndex] = useState(0);

  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;

  const mobileImages = [
    getAssetUrl("media/grandma_tel.png"),
    getAssetUrl("media/team.png"),
    getAssetUrl("media/woman.jpg"),
  ];

  // SLIDESHOW TEXT
  const slidesData = [
    "Ihre zertifizierte Betreuungvermittlung: Passgenaue Pflegekräfte direkt in Ihrer Nähe.",
    "Abrechnung über die Pflegekasse (Entlastungsbetrag & Verhinderungspflege) möglich.",
    "Lückenlos geprüfte Qualifikationen, Deutschkenntnisse und persönliche Eignung.",
    "Rechtssichere Vermittlung für ein selbstbestimmtes Leben im eigenen Zuhause.",
  ];

  const navigate = useNavigate();

  // TIMER SLIDESHOW PHRASES
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prevSlide) => (prevSlide + 1) % slidesData.length);
    }, 10000);

    return () => clearInterval(timer);
  }, [slidesData.length]);

  // TIMER SLIDESHOW IMAGES MOBILE
  useEffect(() => {
    const imageTimer = setInterval(() => {
      setCurrentImageIndex(
        (prevIndex) => (prevIndex + 1) % mobileImages.length,
      );
    }, 6000);

    return () => clearInterval(imageTimer);
  }, [mobileImages.length]);

  // FUNCTIONS
  const toRegist = () => {
    navigate("/carematch/registration");
  };

  const toLogin = () => {
    navigate("/carematch/login");
  };

  return (
    <div className="body-div telecare-content">
      <Navbar />
      {/* SECTION INTRO */}
      <div className="telecare-section" id="telecare-intro">
        <div className="slider-container">
          {slidesData.map((text, index) => (
            <div
              key={index}
              className={`slide ${index === currentSlide ? "active" : ""}`}
            >
              <h3>{text}</h3>
            </div>
          ))}
        </div>
        <div className="intro-div">
          <div className="text-area">
            <h2>
              CareMatch Vermittlung <br />
              Qualifizierte Betreuung für Ihr Zuhause
            </h2>
            <b>
              <ul>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Maßgeschneiderte
                  Vermittlung von Betreuungspersonen.
                </li>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Direkte
                  Abrechnungsmöglichkeit mit allen Pflegekassen.
                </li>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Geprüfte
                  Anforderungsprofile für spezifische Krankheitsbilder.
                </li>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Persönliche Begleitung &
                  rechtssichere Verträge.
                </li>
              </ul>
            </b>

            {/* MOBILE BILDER-SLIDESHOW */}
            <div className="intro-img-div-mobile">
              <div className="img-container">
                {mobileImages.map((src, index) => (
                  <img
                    key={index}
                    src={src}
                    alt={`Slide ${index + 1}`}
                    className={`intro-img ${
                      index === currentImageIndex ? "active" : ""
                    }`}
                  />
                ))}
              </div>
            </div>

            <div className="intro-button-div">
              <button onClick={toRegist}>
                Betreuung anfragen <FontAwesomeIcon icon={faThumbsUp} />
              </button>
              <button onClick={toLogin}>
                Partner-Login <FontAwesomeIcon icon={faUserCheck} />
              </button>
            </div>
          </div>
          <div className="intro-img-div-desktop">
            <div className="img-container">
              {mobileImages.map((src, index) => (
                <img
                  key={index}
                  src={src}
                  alt={`Slide ${index + 1}`}
                  className={`intro-img ${
                    index === currentImageIndex ? "active" : ""
                  }`}
                />
              ))}
            </div>
          </div>
        </div>
      </div>

      {/** STATISTICS DIV */}
      <div className="quick_statistic_div">
        <h2>
          Mehr als{" "}
          <span className="stat-number">500+ erfolgreiche Vermittlungen</span>{" "}
          zwischen Familien und qualifizierten Pflegekräften
          <FontAwesomeIcon icon={faHeart} />
        </h2>
      </div>

      {/* SCENARIO SECTION */}
      <div className="telecare-section scenario-section" id="scenario-section">
        <div className="scenario-card">
          <div className="scenario-img">
            <img
              src={getAssetUrl("media/scenario_think.jpg")}
              alt="Entlastung für Angehörige"
            />
          </div>
          <p>
            <i>
              "Dank CareMatch haben wir innerhalb weniger Tage eine liebevolle
              Betreuungskraft für meine Mutter gefunden. Die Abrechnung über die
              Pflegekasse hat die Agentur komplett begleitet."
            </i>
          </p>
        </div>
        <div className="scenario-card" id="scenario-card-right">
          <p>
            <i>
              "Als zertifizierte Betreuungskraft schätze ich die transparente
              Vermittlung. Das Anforderungsprofil passte exakt zu meinen
              Erfahrungen in der Demenzbetreuung."
            </i>
          </p>
          <div className="scenario-img">
            <img
              src={getAssetUrl("media/scenario_glad.jpg")}
              alt="Qualifizierte Betreuung"
            />
          </div>
        </div>
        <div className="scenario-card">
          <div className="scenario-img">
            <img
              src={getAssetUrl("media/scenario_granny.jpg")}
              alt="Eigenständiges Leben"
            />
          </div>
          <p>
            <i>
              "Endlich Unterstützung im Haushalt und bei Einkäufen, ohne meine
              eigene Wohnung aufgeben zu müssen. Ein echter Gewinn an
              Lebensqualität."
            </i>
          </p>
        </div>
      </div>

      {/* WORKING SECTION */}
      <div className="telecare-section section" id="working">
        <h2>Unser Vermittlungsprozess in 3 Schritten</h2>
        <div className="working-grid">
          <div className="working-card card">
            <h2>1. Bedarf erfassen</h2>
            <p>
              Legen Sie Ihr Profil an und geben Sie spezifische Kriterien wie
              Region, Betreuungsbedarf, Sprachen und gewünschte Dienstleistungen
              an.
            </p>
          </div>
          <div className="working-card card">
            <h2>2. Passgenaue Vermittlung</h2>
            <p>
              Unser Matching-System schlägt Ihnen qualifizierte
              Betreuungspersonen vor, die exakt zu Ihren medizinischen und
              persönlichen Anforderungen passen.
            </p>
          </div>
          <div className="working-card card">
            <h2>3. Rechtssicher starten</h2>
            <p>
              Wählen Sie den passenden Kandidaten aus. Wir unterstützen bei den
              Vereinbarungen und der Beantragung der Budgets bei der
              Pflegekasse.
            </p>
          </div>
        </div>

        <div className="explain-div">
          <h3>
            Geprüfte Qualität & Qualifikation{" "}
            <FontAwesomeIcon icon={faCertificate} />
          </h3>
          <p>
            Alle vermittelten Betreuungspersonen durchlaufen einen Prüfprozess.
            Wir stellen sicher, dass Erfahrungen im Bereich Demenz,
            Rollstuhlversorgung oder Haushaltsführung vorliegen.
          </p>
          <h3>
            Rechtssicherheit & Transparenz{" "}
            <FontAwesomeIcon icon={faFileContract} />
          </h3>
          <p>
            Wir legen Wert auf 100 % konforme Vermittlungsverhältnisse. Bei uns
            gibt es keine versteckten Gebühren oder unklaren
            Vertragsverhältnisse.
          </p>
          <h3>
            Pflegekassen-Entlastung nutzen{" "}
            <FontAwesomeIcon icon={faHandshakeAngle} />
          </h3>
          <p>
            Nutzen Sie Ihren monatlichen Entlastungsbetrag (€125+) sowie Gelder
            der Verhinderungspflege. Unsere Vermittlung zeigt auf, welche
            Leistungen übernommen werden.
          </p>
          <h3>
            Datenschutz & Privatsphäre <FontAwesomeIcon icon={faShieldHalved} />
          </h3>
          <p>
            Ihre sensiblen Daten werden vertraulich behandelt und nur für den
            Vermittlungszweck auf nach DSGVO zertifizierten deutschen Servern
            verarbeitet.
          </p>
        </div>
      </div>

      {/* Q & A SECTION */}
      <div className="telecare-section" id="QAndA">
        <h2>Häufig gestellte Fragen zur Vermittlung</h2>
        <details>
          <summary>
            <FontAwesomeIcon icon={faCircleQuestion} />
            Wer übernimmt die Kosten für die Betreuungsvermittlung?
          </summary>
          <p>
            Bei Vorliegen eines Pflegegrads können viele Leistungen über den
            Entlastungsbetrag oder die Verhinderungspflege direkt mit der
            Pflegekasse abgerechnet werden.
          </p>
        </details>
        <details>
          <summary>
            <FontAwesomeIcon icon={faCircleQuestion} />
            Wie schnell kann eine Betreuungsperson vermittelt werden?
          </summary>
          <p>
            Nach der Registrierung und der Eingabe Ihrer Filterkriterien im
            Dashboard erhalten Sie umgehend passende Vorschläge aus Ihrer
            Region.
          </p>
        </details>
        <details>
          <summary>
            <FontAwesomeIcon icon={faCircleQuestion} />
            Wie werden die Betreuungspersonen überprüft?
          </summary>
          <p>
            Wir prüfen Sprachkenntnisse, Referenzen, Qualifikationsnachweise
            sowie die Eignung für spezifische Einschränkungen (z.B. Demenz oder
            Gehbehinderung).
          </p>
        </details>
        <details>
          <summary>
            <FontAwesomeIcon icon={faCircleQuestion} />
            Kann die Betreuungsperson gewechselt werden?
          </summary>
          <p>
            Ja, sollte die Chemie einmal nicht stimmen, können Sie jederzeit
            über das Dashboard neue Vermittlungsvorschläge anfordern.
          </p>
        </details>
      </div>

      <Footer />
    </div>
  );
}

export default CareMatchLanding;
