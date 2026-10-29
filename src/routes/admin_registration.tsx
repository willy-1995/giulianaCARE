import React, { useState } from "react";
import "./AdminRegistration.scss";
import { API_BASE } from "../assets/base_url";

export default function AdminRegistration() {
  // Zustand für den Schutz per Master-PIN
  const [isUnlocked, setIsUnlocked] = useState(false);
  const [masterPinInput, setMasterPinInput] = useState("");
  const [modalError, setModalError] = useState<string | null>(null);

  // Zustand für das eigentliche Registrierungsformular
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [pin, setPin] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  // Definiere hier deine geheime Master-PIN für den Zugriff auf die Seite
  // (In einem echten Projekt könntest du das auch gegen eine Backend-API prüfen)
  const MASTER_SECRET_PIN = "998877";

  const handleUnlock = (e: React.FormEvent) => {
    e.preventDefault();
    setModalError(null);

    if (masterPinInput === MASTER_SECRET_PIN) {
      setIsUnlocked(true);
    } else {
      setModalError("Falsche Master-PIN. Zugriff verweigert.");
      setMasterPinInput("");
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    if (pin.length !== 6) {
      setError("Die neue Admin-PIN muss genau 6 Stellen haben.");
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(`${API_BASE}/api/admin_crud.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password, pin }),
      });

      const data = await response.json();

      if (data.success) {
        setSuccess(
          "Admin erfolgreich registriert! Du kannst dich jetzt einloggen.",
        );
        setEmail("");
        setPassword("");
        setPin("");
      } else {
        setError(data.message || "Registrierung fehlgeschlagen.");
      }
    } catch (err) {
      setError("Ein Netzwerkfehler ist aufgetreten.");
    } finally {
      setLoading(false);
    }
  };

  // 1. Wenn noch nicht entsperrt, zeige NUR das PIN-Modal
  if (!isUnlocked) {
    return (
      <div className="admin-register-container">
        <div className="admin-register-form">
          <h2>Zugang geschützt</h2>
          <p
            style={{ fontSize: "0.9rem", color: "#666", marginBottom: "1rem" }}
          >
            Bitte gib die Master-PIN ein, um das Registrierungsformular
            anzuzeigen.
          </p>

          {modalError && (
            <div
              className="error-message"
              style={{ color: "red", marginBottom: "1rem" }}
            >
              {modalError}
            </div>
          )}

          <form onSubmit={handleUnlock}>
            <div className="form-group">
              <label>Master-PIN</label>
              <input
                type="password"
                maxLength={6}
                value={masterPinInput}
                onChange={(e) =>
                  setMasterPinInput(e.target.value.replace(/\D/g, ""))
                }
                required
                placeholder="••••••"
                autoFocus
              />
            </div>
            <button type="submit">Entsperren</button>
          </form>
        </div>
      </div>
    );
  }

  // 2. Sobald die PIN korrekt war, wird das echte Registrierungsformular freigegeben
  return (
    <div className="admin-register-container">
      <form onSubmit={handleRegister} className="admin-register-form">
        <h2>Admin Registrierung</h2>

        {error && <div className="error-message">{error}</div>}
        {success && (
          <div
            className="success-message"
            style={{ color: "green", marginBottom: "1rem" }}
          >
            {success}
          </div>
        )}

        <div className="form-group">
          <label>E-Mail-Adresse</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            placeholder="admin@giuliana-care.de"
          />
        </div>

        <div className="form-group">
          <label>Passwort</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            placeholder="••••••••"
          />
        </div>

        <div className="form-group">
          <label>6-stellige PIN für den Admin</label>
          <input
            type="text"
            maxLength={6}
            value={pin}
            onChange={(e) => setPin(e.target.value.replace(/\D/g, ""))}
            required
            placeholder="123456"
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading ? "Wird registriert..." : "Admin erstellen"}
        </button>
      </form>
    </div>
  );
}
