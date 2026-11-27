import React, { useState, useEffect, useRef } from "react";
import { Link } from "react-router-dom";
import { useNavigate } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import {
  faTimes,
  faCheck,
  faPhone,
  faEnvelope,
  faXmark,
  faThumbsUp,
  faUsers,
  faPhoneVolume,
  faTriangleExclamation,
  faHandHoldingHeart,
  faShieldHalved,
  faRobot,
  faCircleQuestion,
  faHandshakeAngle,
  faHeart,
} from "@fortawesome/free-solid-svg-icons";
import Navbar from "./components/navbar";
import Footer from "./components/footer";
import TestCallForm from "../assets/testCallForm";
import "./styles/main.scss";
import "./styles/landing.scss";
import { loadClients } from "../assets/loader";
import { loadVapiStats } from "../assets/loader";
import { price1, price2, price3, corpName } from "../assets/constants";

function Landinpage() {
  const [isOpen, setIsOpen] = useState(false);
  const [mobileMenue, setMobileMenue] = useState(false);
  const [currentSlide, setCurrentSlide] = useState(0);
  const [modal, setModal] = useState(false);

  // State & Bilder-Array für die Mobile-Image-Slideshow
  const [currentImageIndex, setCurrentImageIndex] = useState(0);

  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;

  //PICS
  const telecareIMG: string = getAssetUrl("media/grandma_tel.png");
  const carematchIMG: string = getAssetUrl("media/team.png");
  const aboutUsIMG: string = getAssetUrl("media/aboutMe.png");
  const guideIMG: string = getAssetUrl("media/guide.jpg");

  // 1. Zielwerte aus der API
  const [targetCount, setTargetCount] = useState<number>(14);
  const [targetMinutes, setTargetMinutes] = useState<number>(0);

  // 2. Die animierten Werte für die Anzeige
  const [displayCount, setDisplayCount] = useState<number>(0);
  const [displayMinutes, setDisplayMinutes] = useState<number>(0);

  // Ref für das HTML-Element (zum Erkennen des Scrollens)
  const statsRef = useRef<HTMLDivElement>(null);

  // Ref um sicherzustellen, dass die Animation nur einmal abläuft
  const hasAnimated = useRef<boolean>(false);

  // SLIDESHOW TEXT
  const slidesData = [
    "Ob vor Ort oder telefonisch - mit giulianaCARE unterstützen wir Seniorinnen und Senioren bei einem eigenständigen Leben und entlasten Angehörige bei der Care-Arbeit.",
    "giulianaCARE Alltagsassistenz bringt Assistenzkräfte und Assistenzbedürftige zusammen. Mit Empathie und Liebe zum Menschen unterstützen wir Seniorinnen und Senioren dabei, den Alltag zu bewältigen.",
    "Die giulianaCARE Telefonbetreuung hält Angehörigen den Rücken frei und begleitet Seniorinnen und Senioren bei der Eigenständigkeit zu Hause. Automatische Sicherheitsanrufe 365 Tage im Jahr. Keine Installation. Keine Extra-Hardware.",
  ];

  const navigate = useNavigate();

  // TIMER SLIDESHOW PHRASES
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prevSlide) => (prevSlide + 1) % slidesData.length);
    }, 10000);

    return () => clearInterval(timer);
  }, [slidesData.length]);

  //LOAD STATS
  // A) Daten aus der API laden (Klienten-Anzahl & Vapi-Minuten)
  useEffect(() => {
    const fetchStatsData = async () => {
      // 1. Klienten laden
      const clientResult = await loadClients(() => {});
      if (
        clientResult &&
        clientResult.success &&
        Array.isArray(clientResult.data)
      ) {
        const realCount = clientResult.data.length;
        setTargetCount(realCount >= 15 ? realCount : 14);
      }

      // 2. Vapi-Minuten laden
      const vapiResult = await loadVapiStats();
      if (vapiResult && vapiResult.success) {
        setTargetMinutes(vapiResult.totalMinutes || 0);
      }
    };

    fetchStatsData();
  }, []);

  // B) Scroll-Erkennung & synchrone Hochzähl-Animation
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        const [entry] = entries;

        if (entry.isIntersecting && !hasAnimated.current) {
          hasAnimated.current = true;

          const duration = 1500; // Dauer der Animation in ms (1.5 Sekunden)
          const steps = 50; // Anzahl der Zwischenschritte für flüssige Animation
          const stepTime = duration / steps;
          let currentStep = 0;

          const timer = setInterval(() => {
            currentStep++;
            const progress = currentStep / steps;

            // Werte schrittweise berechnen
            setDisplayCount(Math.round(targetCount * progress));
            setDisplayMinutes(Math.round(targetMinutes * progress));

            if (currentStep >= steps) {
              setDisplayCount(targetCount);
              setDisplayMinutes(targetMinutes);
              clearInterval(timer);
            }
          }, stepTime);
        }
      },
      { threshold: 0.3 },
    );

    if (statsRef.current) {
      observer.observe(statsRef.current);
    }

    return () => observer.disconnect();
  }, [targetCount, targetMinutes]);

  // FUNCTIONS
  const openModal = () => {
    setModal(true);
  };

  const closeModal = () => {
    setModal(false);
  };

  const toRegist = () => {
    navigate("/registration");
  };

  return (
    <div className="body-div telecare-content">
      <Navbar />
      {/* SECTION INTRO */}
      <div className="section">
        <div className="welcome-text">
          <h1>giulianaCARE - Dein Partner in der Seniorenbetreuung</h1>
          <div className="slider-container">
            {slidesData.map((text, index) => (
              <div
                key={index}
                className={`slide ${index === currentSlide ? "active" : ""}`}
              >
                <h2>{text}</h2>
              </div>
            ))}
          </div>
        </div>
        {/*SLIDESHOW */}

        <div className="menue-grid">
          {/*MENUE ITEMS */}

          <div className="menue-item">
            <h2>Alltagsassistenz</h2>
            <div className="menue-img-div">
              {" "}
              <img src={carematchIMG} alt="Alltagsassistenz" />
            </div>
            <div className="menue-item-text">
              <p>
                Hier finden Alltagsassisten*innen und Assistenznehmer*innen
                zusammen
              </p>
              <Link to={"/carematch_dashboard"} className="menue-button">
                Jetzt finden!
              </Link>
            </div>
          </div>

          <div className="menue-item">
            <h2>Telefonbetreuung</h2>
            <div className="menue-img-div">
              {" "}
              <img src={telecareIMG} alt="Telefonbetreuung" />
            </div>
            <div className="menue-item-text">
              <p>Tägliche Betreuungsanrufe mit Medikamenterinnerung</p>
              <Link to={"/telecare"} className="menue-button">
                Erfahre mehr!
              </Link>
            </div>
          </div>

          <div className="menue-item">
            <h2>Ratgeber und Veröffentlichungen</h2>
            <div className="menue-img-div">
              {" "}
              <img src={guideIMG} alt="Alltagsassistenz" />
            </div>
            <div className="menue-item-text">
              <p>
                Ratgeber und Veröffentlichungen rund um die Seniorenbetreuung,
                Pflege und das Leben im Alter
              </p>
              <Link to={"/carematch_dashboard"} className="menue-button">
                Jetzt finden!
              </Link>
            </div>
          </div>
        </div>
      </div>
      {/*SECTION */}
      <div className="section">
        <div className="about-us">
          <div className="about-us-img-div">
            <img src={aboutUsIMG} alt="Über uns" />
          </div>
          <div className="about-us-text">
            <h3>
              <i>giulianaCARE - In Gedenken an meine geliebte Oma Giuliana</i>
            </h3>
            <p>
              <i>
                Lorem ipsum dolor sit amet, consectetur adipisicing elit.
                Perspiciatis molestias fugit cupiditate necessitatibus illum
                reiciendis, velit quos laboriosam? Repellendus at mollitia fuga
                minima. Quis, possimus quod quidem dicta, placeat maxime
                officiis neque iure consequatur saepe quas? Dolores tempore
                soluta maiores accusamus suscipit esse quos ullam ut facilis
                numquam officiis sunt vel, aperiam facere voluptates, culpa est
                modi exercitationem fugiat aspernatur fugit voluptas sint quia?
                Maxime cum corporis, ullam doloremque repellat ea obcaecati
                repellendus vero dolorem similique nesciunt molestiae dolores,
                veritatis id natus mollitia quibusdam voluptate dolore illum
                facere debitis a quae, inventore fuga! Enim, in nihil? Omnis nam
                quis totam. <br />
                <br /> - Lucas Wingenfeld (Gründer)
              </i>
            </p>
          </div>
        </div>
      </div>
      <div className="section">
        <div id="QAndA">
          <h2>Häufig gestellte Fragen</h2>
          <h3>giulianaCARE Telefonbetreuung</h3>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Brauche ich extra Hardware oder muss ich Software installieren?
            </summary>
            <p>
              Nein, du brauchst keine extra Hardware und musst keine extra
              Software installieren. Unser Sprachassistent ruft deinen
              Angehörigen direkt auf dem Telefon oder Handy an.
            </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} /> Kann ich den
              Sprachassistenten auch pausieren?
            </summary>
            <p>
              Ja, der Sprachassistent lässt sich jederzeit mit nur einem Klick
              deaktivieren und reaktivieren.
            </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Kann ich die Anrufzeiten flexibel ändern?
            </summary>
            <p>Ja, du kannst die Anrufzeiten flexibel anpassen. </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Welche Medikamente und Informationen kann für die Anrufe angeben?
            </summary>
            <p>
              Du kannst jedes Medikament mit Menge oder Stückzahl angeben. Zudem
              kannst du Zusatzinformationen, wie z.B. Erinnerung zur
              Flüssigkeitszufuhr, Terminerinnerungen oder Gedächtnisabfragen
              angeben.
            </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Wie sind die Kündigungsfristen für mein Abo?
            </summary>
            <p>
              Der Vertrag wird auf unbestimmte Zeit geschlossen und kann
              jederzeit mit einer Frist von 14 Tagen zum Monatsende gekündigt
              werden.
            </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Was passiert mit meinen Daten?
            </summary>
            <p>
              Deine Daten bleiben in Deutschland, dank inländischer
              Server-Standorte. giulianaCARE verzichtet auf Tracking, Cookies
              und die unerlaubte Weitergabe ihrer Daten an Dritte.
            </p>
          </details>
          {/* Q AND A CAREMATCH */}

          <h3>giulianaCARE Alltagsassistenz</h3>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Was ist giulianaCARE Alltagsassistenz?
            </summary>
            <p>
              giulianaCARE Alltagsassistenz verbindet Alltagsassistent*innen und
              assistenzbedürftige Seniorinnen und Senioren bzw. deren
              Angehörige. Dabei wirken wir als reine Vermittlungsplattform.
            </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Welche Leistungen werden angeboten?
            </summary>
            <p>
              Alltagsassistent*innen geben ihre angebotenen Leistungen selbst
              an. Du kannst gezielt nach deinen Bedürfnissen filtern.
            </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Übernimmt die Pflegekasse die Kosten?
            </summary>
            <p>
              Die Pflegekasse übernimmt Kosten für Alltagsassistenz bzw.
              Alltagsbegleitung ab Pflegegrad 1 über den Entlastungsbetrag (§
              45b SGB XI). Die Alltagsassitent*innen geben selbst an, ob die
              Leistung abbrechungsfähig ist und müssen die nötige Qualifikation
              uns gegenüber nachweisen.
            </p>
          </details>
          <details>
            <summary>
              <FontAwesomeIcon icon={faCircleQuestion} />
              Ich will Alltagsassistent werden, was muss ich tun?
            </summary>
            <p>
              Registriere dich einfach in wenigen Schritten bei der giulianaCARE
              Alltagsassistenz als Assistenzkraft und biete deine Leistungen an.
              Mit der{" "}
              <b>
                Basisqualifikation zur Alltagsbegleitung / Betreuungskraft nach
                § 45a SGB XI
              </b>{" "}
              kannst du mit der Pflegekasse abbrechnen und wirst so attraktiver
              für deine Klient*innen
            </p>
          </details>
        </div>
      </div>

      <Footer />

      {modal && (
        <div className="modal-overlay">
          <div className="modal">
            <button onClick={closeModal} className="modal-button">
              Abbrechen
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default Landinpage;
