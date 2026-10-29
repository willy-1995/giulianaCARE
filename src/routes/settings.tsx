import { useNavigate } from "react-router-dom";
import { Link } from "react-router-dom";
import { useState, useEffect } from "react";
import SubNavbar from "./components/navbar_sub";
import Footer from "./components/footer";
import { updateUser } from "../assets/updater";
import "./styles/settings.scss";
import "./styles/main.scss";

export default function Settings() {
  // STATES
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);

  // Modals
  const [isProfileModalOpen, setIsProfileModalOpen] = useState(false);
  const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);

  // Statusanzeigen
  const [subscriptionStatus, setSubscriptionStatus] = useState<string>("");
  const [cancelMessage, setCancelMessage] = useState<string>("");

  // Form-States
  const [formData, setFormData] = useState({
    email: "",
    price: "",
    country: "",
    area_code: "",
  });

  const [passwordData, setPasswordData] = useState({
    current_password: "",
    new_password: "",
  });

  const [profileMessage, setProfileMessage] = useState("");
  const [passwordMessage, setPasswordMessage] = useState("");

  // User-Daten & Abo-Status beim Laden abrufen
  useEffect(() => {
    const fetchUserData = async () => {
      try {
        const token = localStorage.getItem("token");
        if (!token) return;

        const response = await fetch(
          "https://giuliana-care.de/api/users_manager.php",
          {
            method: "GET",
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
            },
          },
        );

        const data = await response.json();
        if (data.success && data.user) {
          setFormData({
            email: data.user.email || "",
            price: data.user.price || "",
            country: data.user.country || "",
            area_code: data.user.area_code || "",
          });
          setSubscriptionStatus(data.user.subscription_status || "");
        }
      } catch (err) {
        console.error("Fehler beim Laden der Benutzerdaten:", err);
      }
    };

    fetchUserData();
  }, []);

  // Logout
  const handleLogout = () => {
    localStorage.removeItem("token");
    navigate("/login");
  };

  // Handlers für Inputs
  const handleProfileChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handlePasswordChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setPasswordData((prev) => ({ ...prev, [name]: value }));
  };

  // Profil-Daten aktualisieren
  const handleProfileUpdate = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    const result = await updateUser(formData, setLoading);

    if (result.success) {
      setProfileMessage(result.message || "Profil erfolgreich aktualisiert!");
      setTimeout(() => {
        setIsProfileModalOpen(false);
        setProfileMessage("");
      }, 1500);
    } else {
      setProfileMessage(
        result.message || "Fehler beim Aktualisieren des Profils.",
      );
    }
  };

  // Passwort aktualisieren
  const handlePasswordUpdate = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    if (!passwordData.current_password || !passwordData.new_password) {
      setPasswordMessage("Bitte fülle beide Passwortfelder aus.");
      return;
    }

    const payload = {
      ...formData,
      current_password: passwordData.current_password,
      new_password: passwordData.new_password,
    };

    const result = await updateUser(payload, setLoading);

    if (result.success) {
      setPasswordMessage("Passwort erfolgreich geändert!");
      setTimeout(() => {
        setIsPasswordModalOpen(false);
        setPasswordMessage("");
        setPasswordData({ current_password: "", new_password: "" });
      }, 1500);
    } else {
      setPasswordMessage(result.message || "Fehler beim Ändern des Passworts.");
    }
  };

  // Abonnement kündigen (Gemäß AGB §6)
  const handleCancelSubscription = async () => {
    const confirmed = window.confirm(
      "Möchtest du dein Abonnement unter Einhaltung der Kündigungsfrist (14 Tage zum Monatsende) kündigen?\n\n" +
        "Dein Account und alle automatischen Anrufe bleiben bis zum Ablauf der Frist vollumfänglich aktiv.",
    );

    if (!confirmed) return;

    setLoading(true);
    try {
      const token = localStorage.getItem("token");
      const response = await fetch(
        "https://giuliana-care.de/api/cancel_subscription.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        },
      );

      const data = await response.json();

      if (data.success) {
        setSubscriptionStatus("cancel_pending");
        setCancelMessage(
          `Dein Abo ist gekündigt und läuft zum ${data.effective_date} aus.`,
        );
        alert(data.message);
      } else {
        alert(data.message || "Fehler beim Kündigen des Abonnements.");
      }
    } catch (err) {
      alert("Netzwerkfehler beim Kündigen des Abonnements.");
    } finally {
      setLoading(false);
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
            onClick={() => setIsProfileModalOpen(true)}
            className="logout setting-button"
          >
            Daten bearbeiten
          </button>

          <button
            onClick={() => setIsPasswordModalOpen(true)}
            className="logout setting-button"
          >
            Passwort ändern
          </button>

          <button onClick={handleLogout} className="logout setting-button">
            Ausloggen
          </button>
        </div>

        {/* Abo-Verwaltung & Kündigung */}
        <div className="setting-section">
          <h3>Abonnement & Mitgliedschaft</h3>

          {subscriptionStatus === "cancel_pending" ? (
            <p className="status-info warning">
              Dein Abo ist gekündigt. Du kannst den Service noch bis zum Ende
              der Kündigungsfrist vollumfänglich nutzen.
            </p>
          ) : subscriptionStatus === "canceled" ? (
            <p className="status-info danger">
              Dein Abonnement ist abgelaufen.
            </p>
          ) : (
            <button
              onClick={handleCancelSubscription}
              className="logout setting-button"
              disabled={loading}
            >
              {loading ? "Wird verarbeitet..." : "Abo kündigen"}
            </button>
          )}

          {cancelMessage && <p className="status-message">{cancelMessage}</p>}
        </div>
      </div>

      {/* MODAL 1: PROFIL DATEN ÄNDERN */}
      {isProfileModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h3 className="modal-heading">Daten ändern</h3>

            {profileMessage && (
              <p className="modal-message">{profileMessage}</p>
            )}

            <form onSubmit={handleProfileUpdate}>
              <div className="form-div">
                <label>
                  E-Mail
                  <input
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleProfileChange}
                    placeholder="Deine E-Mail"
                    required
                  />
                </label>

                <label>
                  Betreuungspaket
                  <select
                    name="price"
                    value={formData.price}
                    onChange={handleProfileChange}
                  >
                    <option value="" disabled hidden>
                      Betreuungspaket wählen
                    </option>
                    <option value="sicherheit">
                      Sicherheit 19€ - 1 Anruf pro Tag
                    </option>
                    <option value="gutBetreut">
                      Gut betreut 26€ - 2 Anrufe pro Tag
                    </option>
                    <option value="rundumSorglos">
                      Rundum Sorglos 32€ - 3 Anrufe pro Tag
                    </option>
                  </select>
                </label>

                <label>
                  Land
                  <select
                    name="country"
                    value={formData.country}
                    onChange={handleProfileChange}
                  >
                    <option value="" disabled hidden>
                      Land wählen
                    </option>
                    <option value="Deutschland">Deutschland</option>
                    <option value="Österreich">Österreich</option>
                    <option value="Schweiz">Schweiz</option>
                  </select>
                </label>

                <label>
                  Postleitzahl
                  <input
                    type="text"
                    name="area_code"
                    value={formData.area_code}
                    onChange={handleProfileChange}
                    placeholder="Postleitzahl"
                    maxLength={5}
                  />
                </label>
              </div>

              <div className="modal-actions">
                <button
                  type="button"
                  className="cancel-btn"
                  onClick={() => {
                    setIsProfileModalOpen(false);
                    setProfileMessage("");
                  }}
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

      {/* MODAL 2: PASSWORT ÄNDERN */}
      {isPasswordModalOpen && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h3 className="modal-heading">Passwort ändern</h3>

            {passwordMessage && (
              <p className="modal-message">{passwordMessage}</p>
            )}

            <form onSubmit={handlePasswordUpdate}>
              <div className="form-div">
                <label>
                  Aktuelles Passwort
                  <input
                    type="password"
                    name="current_password"
                    value={passwordData.current_password}
                    onChange={handlePasswordChange}
                    placeholder="Aktuelles Passwort"
                    required
                  />
                </label>

                <label>
                  Neues Passwort
                  <input
                    type="password"
                    name="new_password"
                    value={passwordData.new_password}
                    onChange={handlePasswordChange}
                    placeholder="Neues Passwort"
                    required
                  />
                </label>
              </div>

              <div className="modal-actions">
                <button
                  type="button"
                  className="cancel-btn"
                  onClick={() => {
                    setIsPasswordModalOpen(false);
                    setPasswordMessage("");
                    setPasswordData({ current_password: "", new_password: "" });
                  }}
                >
                  Abbrechen
                </button>
                <button type="submit" className="save-btn" disabled={loading}>
                  {loading ? "Speichere..." : "Passwort speichern"}
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
