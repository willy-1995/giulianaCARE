import { Link, useLocation } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import {
  faTimes,
  faCheck,
  faPhone,
  faEnvelope,
  faHandshake,
} from "@fortawesome/free-solid-svg-icons";

function Footer() {
  const isProtectedSettings = location.pathname === "/settings";
  const isProtectedDashboard = location.pathname === "/dashboard";
  return (
    <div className="component-div footer">
      <div className="footer-content">
        <div className="contact-div">
          <h2>Kontakt</h2>

          <a
            href="https://wa.me/4915141650792"
            target="_blank"
            rel="noopener noreferrer"
          >
            <h3>
              <FontAwesomeIcon icon={faWhatsapp} /> Whatsapp
            </h3>
          </a>
          <Link to={"/request"}>
            <h3>
              <FontAwesomeIcon icon={faEnvelope} /> E-Mail
            </h3>
          </Link>
          {/*
          <Link to={"/salespartnership"}>
            <h3>
              <FontAwesomeIcon icon={faHandshake} /> Vertriebspartner werden
            </h3>
          </Link>
          */}
        </div>
        <footer>
          <Link to={"/imprint"} className="legal-link">
            Impressum
          </Link>
          <Link to={"/datasecurity"} className="legal-link">
            Datenschutz
          </Link>
          <Link to={"/termsAndConditions"} className="legal-link">
            AGB
          </Link>
        </footer>
        {/*
        
        <div>
          <a
            rel="nofollow"
            href="https://www.exali.de/siegel/giulianaCARE"
            target="_blank"
            title="Weitere Informationen zur IT-Haftpflicht von Lucas Wingenfeld"
          >
            <img
              src="https://www.exali.de/siegel/siegel_de-2_a7a4422a7017c8f964e4ba0e4ca20559.png"
              width="90"
              height="90"
              alt="Weitere Informationen zur IT-Haftpflicht von Lucas Wingenfeld"
              onError={(e) => {
                console.error(
                  "Exali-Siegel konnte nicht geladen werden:",
                  e.currentTarget.src,
                );
              }}
            />
          </a>
        </div>
        
        */}
      </div>

      <p className="right-advise">
        © 2026 giulianaCARE. Alle Rechte vorbehalten.
      </p>
    </div>
  );
}

export default Footer;
