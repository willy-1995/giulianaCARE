import React from "react";
import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import {
  faTimes,
  faCheck,
  faPhone,
  faEnvelope,
  faXmark,
  faThumbsUp,
} from "@fortawesome/free-solid-svg-icons";
import Navbar from "./components/navbar";
import Footer from "./components/footer";
import TestCallForm from "../assets/testCallForm";
import "./styles/main.scss";
import "./styles/telecare.scss";

function TeleCare() {
  const [isOpen, setIsOpen] = useState(false);
  const [mobileMenue, setMobileMenue] = useState(false);
  const [currentSlide, setCurrentSlide] = useState(0);
  const [modal, setModal] = useState(false);

  //SLIDESHOW
  const slidesData = [
    "Tägliche Betreuungsanrufe, Medikamenten- und Terminerinnerungen. 365 Tage",
    "Keine Extra-Geräte. Keine Installation. Die betreute Person muss nur den Hörer abnehmen!",
    "Sorgen Sie präventiv für die Eigenständigkeit ihres Senioren",
  ];

  useEffect(() => {
    // Timer für den Wechsel alle 6 Sekunden (6000ms)
    const timer = setInterval(() => {
      setCurrentSlide((prevSlide) => (prevSlide + 1) % slidesData.length);
    }, 6000);

    // Aufräumen, falls die Komponente unmounted wird
    return () => clearInterval(timer);
  }, [slidesData.length]);

  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;

  //FUNCTIONS
  const openModal = () => {
    setModal(true);
  };

  const closeModal = () => {
    setModal(false);
  };

  return (
    <div className="body-div telecare-content">
      <Navbar />
      {/*SECTION INTRO */}
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
          <p>
            <h3>
              Präventive Betreuung für Senioren <br />{" "}
              <span>Jeden Tag per Telefon</span>
            </h3>
            <p className="intro-text">
              {" "}
              giulianaCare ist die <b>tägliche Telefonbetreuung</b> für
              alleinstehende Seniorinnen und Senioren. Im Gegensatz zum
              Hausnotruf wirkt giulianaCare <b>präventiv</b>. Bis zu drei
              tägliche, freundliche SicherheitsAnrufe. Und geht keiner ran,
              wirst Du sofort benachrichtigt.
            </p>
            <br />
            <br />
            <b>
              <ul>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Unterstützt
                  Eigenständigkeit von Senioren.
                </li>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Entlastet Angehörige bei
                  der Betreuung.
                </li>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Keine Hardware. Keine
                  Installation.
                </li>
                <li>
                  <FontAwesomeIcon icon={faCheck} /> Wirkt präventiv. Jeden Tag.
                </li>
              </ul>
            </b>
            <div className="intro-button-div">
              <button>
                14 Tage kostenlos testen <FontAwesomeIcon icon={faThumbsUp} />
              </button>
              <button onClick={openModal}>
                Gratis Testanruf <FontAwesomeIcon icon={faPhone} />
              </button>
              {/*//////////////////////////////
              Modal for test-call
              ///////////////////////////////*/}
              {modal && (
                <div className="modal-overlay">
                  <div className="modal">
                    <TestCallForm />
                    <button onClick={closeModal}>Abbrechen</button>
                  </div>
                </div>
              )}
            </div>
          </p>
          <div className="intro-img-div">
            <div className="img-container" id="img-1">
              <img
                src={getAssetUrl("media/grandma_tel.png")}
                alt=""
                id="img-grandma"
                className="intro-img"
              />
            </div>
            <div className="img-container" id="img-2">
              <img
                src={getAssetUrl("media/team.png")}
                alt=""
                id="img-womanThink"
                className="intro-img"
              />
            </div>
            <div className="img-container" id="img-3">
              <img
                src={getAssetUrl("media/woman.jpg")}
                alt=""
                id="img-womanThink"
                className="intro-img"
              />
            </div>
          </div>
        </div>
      </div>

      <div className="telecare-section section" id="working">
        <h2>
          So einfach ist der automatische Betreuungsservice von giulianaCare
        </h2>
        <div className="working-grid">
          <div className="working-card card">
            <h2>1. Anmelden</h2>
            <p>
              Registrieren Sie sich bei giulianaCare und wählen Sie bis zu 3
              Anrufzeiten aus. Diese können jederzeit später werden!
            </p>
          </div>
          <div className="working-card card">
            <h2>2. Tägliche Anrufe</h2>
            <p>
              Die zu betreuende Person kriegt täglich bis zu 3 Anrufe, in
              welchem nach dem Wohlbefinden gefragt oder an Wichtiges erinnert
              wird.
            </p>
          </div>
          <div className="working-card card">
            <h2>3. Kontaktiert Angehörige</h2>
            <p>
              Geht niemand ran? Sie erhalten sofort eine Nachricht per SMS und
              E-Mail.
            </p>
          </div>
        </div>
        <div className="video-div">
          <video
            src="/media/placeholder_vid.mp4"
            className="telecare-video"
            controls
          ></video>
        </div>
        <div className="service-info-div">
          <h2>
            Im Gegensatz zum klassischen Hausnotruf beugst Du mit giulianaCare
            Telebetreuung Ernstfällen bequem vor und kannst diese frühzeitig
            erkennen.{" "}
          </h2>
          <div className="table-div">
            <ul className="table-highlight">
              <h2>giulianaCare</h2>
              <li>
                <FontAwesomeIcon icon={faCheck} /> täglich automatisch
              </li>
              <li>
                <FontAwesomeIcon icon={faCheck} /> präventiv
              </li>
              <li>
                <FontAwesomeIcon icon={faCheck} /> jedes Telefon reicht
              </li>
              <li>
                <FontAwesomeIcon icon={faCheck} /> Zusatzfunktionen
              </li>
            </ul>

            <ul>
              <h2>Hausnotruf</h2>
              <li>
                <FontAwesomeIcon icon={faXmark} /> nur per Knopfdruck
              </li>
              <li>
                <FontAwesomeIcon icon={faXmark} /> erst im Notfall
              </li>
              <li>
                <FontAwesomeIcon icon={faXmark} /> Extra-Gerät nötig
              </li>
              <li>
                <FontAwesomeIcon icon={faXmark} /> nur eine Funktion
              </li>
            </ul>
          </div>
        </div>
        <h2 className="price-heading">Unsere Preise</h2>
        <div className="price-div">
          <div className="price-card card">
            <h2>Sicherheit</h2>
            <div className="price"></div>
            <div className="intervall">
              <p>19€</p>
              <span>
                1 Anruf <br /> pro Tag
              </span>
              <button>Jetzt testen</button>
            </div>
          </div>
          <div className="price-card card">
            <h2>Gut betreut</h2>
            <div className="price"></div>
            <div className="intervall price-highlight">
              <p>26€</p>
              <span>
                2 Anrufe <br /> pro Tag
              </span>
              <button>Jetzt testen</button>
            </div>
          </div>
          <div className="price-card card">
            <h2>Rundum sorglos</h2>
            <div className="price"></div>
            <div className="intervall">
              <p>35€</p>
              <span>
                3 Anrufe <br /> pro Tag
              </span>
              <button>Jetzt testen</button>
            </div>
          </div>
        </div>
        <p className="price-disclaimer">
          *Preise gelten pro Monat, monatlich kündbar
        </p>
      </div>
      <Footer />
    </div>
  );
}

export default TeleCare;
