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
} from "@fortawesome/free-solid-svg-icons";
import Navbar from "./components/navbar";
import "./styles/main.scss";
import "./styles/landing.scss";

function Landing() {
  const [isOpen, setIsOpen] = useState(false);
  const [mobileMenue, setMobileMenue] = useState(false);
  const [currentIndex, setCurrentIndex] = useState(0);
  const totalSlides = 3;

  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;

  //SLIDER
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentIndex((prevIndex) => (prevIndex + 1) % totalSlides);
    }, 6000);
    return () => clearInterval(interval); //stop timer if user leaves site
  }, []);

  const toggleMenu = () => setIsOpen(!isOpen);

  // 1. FUNKTION ZUM SCROLLEN ZU EINER ID
  const scrollToSection = (id: string) => {
    // Menü schließen (für Mobile)
    setIsOpen(false);

    // Das Element anhand der ID finden
    const element = document.getElementById(id);

    if (element) {
      // Weiches Scrollen zum Element
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

  //==================
  //NAVBAR/ MENUE
  //==================
  //OPEN MOBILE MENUE
  const openMobileMenue = () => {
    setMobileMenue(true);
  };

  //CLOSE MOBILE MENUE
  const closeMobileMenue = () => {
    setMobileMenue(false);
  };

  //SEND MAIL
  const sendMail = () => {};

  return (
    <div className="body-div landing-content">
      <Navbar />
      <header>
        <div className="header-div">
          <div className="burger-button" onClick={openMobileMenue}>
            <span className="menue-stripe"></span>
            <span className="menue-stripe"></span>
            <span className="menue-stripe"></span>
          </div>
        </div>

        {/* Navbar - Die Klasse 'open' wird mobil hinzugefügt */}
        <nav className="navbar">
          <div className="nav-links-desktop">
            <p
              className="nav-link"
              onClick={() => {
                scrollToSection("overview");
                closeMobileMenue();
              }}
            >
              Übersicht
            </p>
            <p
              className="nav-link"
              onClick={() => {
                scrollToSection("working");
                closeMobileMenue();
              }}
            >
              Wie funktioniert's
            </p>
            <p
              className="nav-link"
              onClick={() => {
                scrollToSection("about_us");
                closeMobileMenue();
              }}
            >
              Über uns
            </p>
            <p
              className="nav-link"
              onClick={() => {
                closeMobileMenue();
                scrollToSection("contact");
              }}
            >
              Kontakt
            </p>
          </div>
          {mobileMenue && (
            <div className="nav-links-mobile">
              <p className="nav-link close-btn" onClick={closeMobileMenue}>
                x
              </p>
              <p
                className="nav-link"
                onClick={() => {
                  scrollToSection("overview");
                  closeMobileMenue();
                }}
              >
                Übersicht
              </p>
              <p
                className="nav-link"
                onClick={() => {
                  scrollToSection("working");
                  closeMobileMenue();
                }}
              >
                Wie funktioniert's
              </p>
              <p
                className="nav-link"
                onClick={() => {
                  scrollToSection("about_us");
                  closeMobileMenue();
                }}
              >
                Über uns
              </p>
              <p
                className="nav-link"
                onClick={() => {
                  closeMobileMenue();
                  scrollToSection("contact");
                }}
              >
                Kontakt
              </p>

              <Link
                to={"/login"}
                className="nav-link"
                onClick={closeMobileMenue}
              >
                Login
              </Link>
              <Link
                to={"/registration"}
                className="nav-link"
                onClick={closeMobileMenue}
              >
                Registrieren
              </Link>
            </div>
          )}
        </nav>
      </header>
      {/*SECTION INTRO */}
      <div className="section landing-section" id="overview">
        <h2>Leistungen</h2>
        <div className="services-div">
          <div className="service-card card">
            <img
              src={getAssetUrl("media/grandma_support.png")}
              alt="telecare"
            />
            <div className="cover">
              <h3>Alltagsassistenz</h3>
            </div>
          </div>
          <div className="service-card card">
            <div className="service-img-div">
              <img src={getAssetUrl("media/grandma_tel.png")} alt="telecare" />
            </div>
          </div>
          <div className="service-card card">
            <div className="service-img-div">
              <img
                src={getAssetUrl("media/grandma_sport.png")}
                alt="telecare"
              />
            </div>
          </div>
        </div>
        <div className="service-div">
          <h2>Weiterbildungen</h2>
          <div className="service-card working-card">
            <h3>Praxismanagement Physiotherapie</h3>
            <div className="service-img-div"></div>
            <p>
              Lorem ipsum dolor, sit amet consectetur adipisicing elit. Dolor
              quaerat officiis, voluptatibus delectus dolore excepturi maiores
              veniam.
              <Link to={"/education"}>Zum Lehrgang</Link>
            </p>
          </div>
          <div className="service-card">
            <h3>Seniorentraining für zu Hause</h3>
            <div className="service-img-div"></div>
            <p>
              Lorem ipsum dolor, sit amet consectetur adipisicing elit. Dolor
              quaerat officiis, voluptatibus delectus dolore excepturi maiores
              veniam.
              <Link to={"/education"}>Zum Lehrgang</Link>
            </p>
          </div>
        </div>
      </div>
      <div className="landing-section section" id="about_us">
        <h2 className="section-heading">Über uns</h2>
        <div className="about_us-div">
          <div className="about-us-img-div">
            <img
              src={getAssetUrl("media/main_img.jpg")}
              alt="image"
              className="img"
            />
          </div>
          <div className="about_us-text-div">
            <div className="text-shaper"></div>
            Lorem ipsum dolor sit amet, consectetur adipisicing elit. Voluptates
            vero libero minus enim assumenda quisquam, porro quas. Est esse
            veniam perferendis nobis, ut sapiente soluta dolorem provident
            labore odio eaque, sit fugiat architecto impedit? Dolor magni sed
            assumenda eius praesentium, asperiores quas nam animi, porro
            inventore officiis nesciunt quod harum, qui cupiditate? Nihil
            tempora architecto eligendi aliquid est laudantium incidunt ex,
            repellendus dolore perspiciatis suscipit tempore laboriosam optio
            consequuntur accusamus praesentium corporis ea amet? Quasi voluptate
            vero repellat natus tenetur, est quidem ea sapiente tempore sit
            laboriosam ex nisi labore iure exercitationem nulla. Autem ullam,
            iure distinctio quisquam illum omnis.
          </div>
        </div>
      </div>

      <div className="section landing-section" id="contact">
        <h2 className="section-heading">Kontakt</h2>
        <div className="contact-div">
          <div className="contact-card">
            <h3>
              <FontAwesomeIcon icon={faPhone} /> Telefonische Beratung
            </h3>
            <p>+4915141650792</p>
            <p>Mo - Do 17:00 - 20:00 Uhr</p>
          </div>
          <div className="contact-card">
            <h3>
              <FontAwesomeIcon icon={faWhatsapp} /> Whatsapp
            </h3>
            <a
              href="https://wa.me/4915141650792"
              target="_blank"
              rel="noopener noreferrer"
            >
              +4915141650792
            </a>
          </div>
          <form onSubmit={sendMail} className="contact-card contact-form">
            <h3>
              <FontAwesomeIcon icon={faEnvelope} /> Per E-Mail
            </h3>
            <label>
              <p>Ihre Nachricht</p>
              <textarea name="message" maxLength={500}></textarea>
            </label>
            <button type="submit" className="landing-button">
              Senden
            </button>
          </form>
        </div>
      </div>
      <footer>
        <div className="footer-div">
          <div className="footer-card">
            <p>Impressum</p>
            <p>Datenschutz</p>
            <p>AGB</p>
          </div>
          <div className="footer-card">
            <h3>Kontakt</h3>
            <p>Telefon:</p>
            <p>Email:</p>
          </div>
        </div>
        <p>© 2026 giulianaCARE. Alle Rechte vorbehalten.</p>
      </footer>
    </div>
  );
}

export default Landing;
