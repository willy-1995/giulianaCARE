import { Link, useLocation } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import {
  faTimes,
  faCheck,
  faPhone,
  faEnvelope,
} from "@fortawesome/free-solid-svg-icons";

function Footer() {
  const isProtectedSettings = location.pathname === "/settings";
  const isProtectedDashboard = location.pathname === "/dashboard";
  return (
    <div className="component-div footer">
      <h2>Kontakt</h2>
      <div className="contact-div">
        <div className="contact-card">
          <a
            href="https://wa.me/4915141650792"
            target="_blank"
            rel="noopener noreferrer"
          >
            <h3>
              <FontAwesomeIcon icon={faWhatsapp} /> Whatsapp
            </h3>
          </a>
          <Link to={"/email"}>
            <h3>
              <FontAwesomeIcon icon={faEnvelope} /> E-Mail
            </h3>
          </Link>
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

export default Footer;
