import { useState } from "react";
import Footer from "./components/footer";
import Navbar from "./components/navbar";
import "./styles/request.scss";
import "./styles/main.scss";

const MAX_LENGTH = 500;

export default function Request() {
  //STATES
  const [formData, setFormData] = useState({
    reference: "",
    email: "",
    tel: "",
    content: "",
  });
  const [status, setStatus] = useState({ type: null, message: "" });
  const [loading, setLoading] = useState(false);

  // Aktualisiert den State bei Formularänderungen
  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // Formular absenden
  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setStatus({ type: null, message: "" });

    try {
      // Daten als FormData aufbereiten, damit PHP sie über $_POST lesen kann
      const body = new FormData();
      body.append("betreff", formData.reference);
      body.append("email", formData.email);
      body.append("telefon", formData.tel);
      body.append("content", formData.content);

      // Pfad zu deinem PHP-Skript anpassen (z. B. "/api/kontakt.php")
      const response = await fetch(
        "http://localhost/giulianaCare/api/request.php",
        {
          method: "POST",
          body: body,
        },
      );

      if (response.ok) {
        setStatus({
          type: "success",
          message: "Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.",
        });
        // Formular zurücksetzen
        setFormData({ reference: "", email: "", tel: "", content: "" });
      } else {
        throw new Error("Fehler beim Senden.");
      }
    } catch (error) {
      setStatus({
        type: "error",
        message: "Beim Versenden der Nachricht ist ein Fehler aufgetreten.",
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="body-div contact-content">
      <Navbar />
      <div className="contact-form-div">
        <h2>Kontaktieren Sie uns</h2>

        {status.message && (
          <p
            className={status.type === "success" ? "success-msg" : "error-msg"}
          >
            {status.message}
          </p>
        )}

        <form onSubmit={handleSubmit} className="contact-form">
          <label htmlFor="betreff" className="contact-label">
            <span>
              Betreff (<i>erforderlich</i>)*
            </span>
            <select
              name="reference"
              id="reference"
              value={formData.reference}
              onChange={handleChange}
              required
            >
              <option value="">-- Bitte wählen --</option>
              <option value="Allgemeine Anfrage">Allgemeine Anfrage</option>
              <option value="Support">Support</option>
              <option value="Feedback">Feedback</option>
            </select>
          </label>

          <label htmlFor="email" className="contact-label">
            <span>
              E-Mail-Adresse (<i>erforderlich</i>)*
            </span>
            <input
              type="email"
              name="email"
              id="email"
              value={formData.email}
              onChange={handleChange}
              required
            />
          </label>

          <label htmlFor="telefon" className="contact-label">
            <span>Telefonnummer</span>
            <input
              type="tel"
              name="tel"
              id="tel"
              value={formData.tel}
              onChange={handleChange}
            />
          </label>

          <label htmlFor="content" className="contact-label">
            <span>
              Nachricht (<i>erforderlich</i>)*
            </span>
            <textarea
              name="content"
              id="content"
              value={formData.content}
              onChange={handleChange}
              maxLength={600}
              required
            />
            <small
              className="char-counter"
              style={{ display: "block", textAlign: "right", marginTop: "4px" }}
            >
              {formData.content.length} / {MAX_LENGTH}
            </small>
          </label>

          <button type="submit" disabled={loading}>
            {loading ? "Wird gesendet..." : "Nachricht absenden"}
          </button>
        </form>
      </div>
      <Footer />
    </div>
  );
}
