import { useNavigate } from "react-router-dom";
import { Link } from "react-router-dom";
import { useState } from "react";
import { useEffect } from "react";
import SubNavbar from "./components/navbar_sub";
import Footer from "./components/footer";
import { deleteUser } from "../assets/deleter";
import { updateUser } from "../assets/updater";
import "./styles/settings.scss";
import "./styles/main.scss";
import { faL } from "@fortawesome/free-solid-svg-icons";

export default function Settings() {
  //STATES
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  // Beispielhafter Form-State für die Benutzerdaten
  const [formData, setFormData] = useState({
    email: "",
    price: "",
    country: "",
    area_code: "",
  });
  const [message, setMessage] = useState("");

  //==========================
  //Functions
  //==========================

  //Logout
  const handleLogout = () => {
    localStorage.removeItem("token");
    navigate("/login");
  };

  //Update Account Data
  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  // Account-Daten aktualisieren
  const handleUpdate = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    const result = await updateUser(formData, setLoading);

    if (result.success) {
      setMessage(result.message || "Profil erfolgreich aktualisiert!");
      setTimeout(() => {
        setIsModalOpen(false);
        setMessage("");
      }, 1500);
    } else {
      setMessage(result.message || "Fehler beim Aktualisieren des Profils.");
    }
  };

  //Delete Account
  const handleDeleteAccount = async () => {
    if (confirm("Abonnement kündigen und Account wirklich löschen?")) {
      const result = await deleteUser(setLoading);

      if (result.success) {
        navigate("/registration", {
          state: {
            message:
              "Dein Account wurde gelöscht und das Abonnement zum nächstmöglichen Zeitpunkt gekündigt.",
          },
        });
      } else {
        // Fehlerbehandlung (z. B. Banner oder Toast anzeigen)
        alert(result.message || "Fehler beim Löschen des Accounts");
      }
    }
  };

  return (
    <div className="body-div settings-div">
      <SubNavbar />
      <div className="nav-div">
        <Link to="/dashboard" className="link">
          <h3>Dashboard</h3>
        </Link>
      </div>
      <div className="setting-sections-div">
        <div className="setting-section">
          <h3>Dein Account</h3>
          <button
            onClick={() => setIsModalOpen(true)}
            className="logout setting-button"
          >
            Bearbeiten
          </button>
          <button onClick={handleLogout} className="logout setting-button">
            Ausloggen
          </button>
        </div>
        <div className="setting-section">
          <h3>Abonnement</h3>
        </div>
        <div className="settings-section">
          <button
            onClick={handleDeleteAccount}
            className="deleteAccount setting-button"
          >
            Kündigen
          </button>
        </div>
      </div>
      {/* MODAL FÜR DATEN-UPDATE */}
      {isModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h3>Daten ändern</h3>

            {message && <p className="modal-message">{message}</p>}

            <form onSubmit={handleUpdate}>
              <div className="form-group">
                <label>E-Mail</label>
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  placeholder="Deine E-Mail"
                />
              </div>

              <div className="form-group">
                <label>Betreuungspaket</label>
                <select
                  name="price"
                  value={formData.price}
                  onChange={handleChange}
                >
                  <option value="" disabled hidden>
                    Paket wählen
                  </option>
                  <option value="" disabled hidden>
                    Betreuungspaket wählen
                  </option>
                  <option value="sicherheit">
                    Sicherheit 19€ - 1 Anruf pro Tag
                  </option>
                  <option value="gutBetreut">
                    Gut bretreut 26€ - 2 Anrufe pro Tag
                  </option>
                  <option value="rundumSorglos">
                    Rundum Sorglos 32€ - 3 Anrufe pro Tag
                  </option>
                </select>
              </div>

              <div className="form-group">
                <label>Land</label>
                <select
                  name="country"
                  value={formData.country}
                  onChange={handleChange}
                >
                  <option value="" disabled hidden>
                    Land wählen
                  </option>
                  <option value="Deutschland">Deutschland</option>
                  <option value="Österreich">Österreich</option>
                  <option value="Schweiz">Schweiz</option>
                </select>
              </div>

              <div className="form-group">
                <label>Postleitzahl</label>
                <input
                  type="text"
                  name="area_code"
                  value={formData.area_code}
                  onChange={handleChange}
                  placeholder="Postleitzahl"
                  maxLength={5}
                />
              </div>

              <div className="modal-actions">
                <button
                  type="button"
                  className="cancel-btn"
                  onClick={() => setIsModalOpen(false)}
                >
                  Abbrechen
                </button>
                <button type="submit" className="save-btn" disabled={loading}>
                  {loading ? "Speichere..." : "Speichern"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      <Footer />
    </div>
  );
}
