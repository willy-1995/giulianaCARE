import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import "./styles/admin_registration.scss";
import { API_BASE } from "../assets/base_url";

type AuthMode = "login" | "register";

export default function AdminRegistration() {
  const navigate = useNavigate();

  // 1. Schutz per Master-PIN
  const [isUnlocked, setIsUnlocked] = useState(false);
  const [masterPinInput, setMasterPinInput] = useState("");
  const [modalError, setModalError] = useState<string | null>(null);
  const [verifyingPin, setVerifyingPin] = useState(false);

  // 2. Modus (Login / Registrierung)
  const [authMode, setAuthMode] = useState<AuthMode>("login");

  // Login-Zustand
  const [loginEmail, setLoginEmail] = useState("");
  const [loginPassword, setLoginPassword] = useState("");
  const [loginError, setLoginError] = useState<string | null>(null);
  const [loginLoading, setLoginLoading] = useState(false);

  // Registrierungs-Zustand
  const [registerEmail, setRegisterEmail] = useState("");
  const [registerPassword, setRegisterPassword] = useState("");
  const [registerPasswordConfirm, setRegisterPasswordConfirm] = useState("");
  const [registerError, setRegisterError] = useState<string | null>(null);
  const [registerSuccess, setRegisterSuccess] = useState<string | null>(null);
  const [registerLoading, setRegisterLoading] = useState(false);

  const handleUnlock = async (e: React.FormEvent) => {
    e.preventDefault();
    setModalError(null);

    const cleanedPin = masterPinInput.trim();
    if (!cleanedPin) {
      setModalError("Bitte gib zuerst deine Master-PIN ein.");
      return;
    }

    setVerifyingPin(true);

    try {
      const response = await fetch(`${API_BASE}/api/admin_verify_pin.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ master_pin: cleanedPin }),
      });

      const contentType = response.headers.get("content-type") || "";
      if (contentType.includes("application/json")) {
        const rawData = await response.text();
        const data = JSON.parse(rawData);

        if (response.ok && data.success) {
          setIsUnlocked(true);
          setMasterPinInput("");
        } else {
          setModalError(
            data.message || "Falsche Master-PIN. Zugriff verweigert.",
          );
          setMasterPinInput("");
        }
      } else {
        const text = await response.text();
        setModalError(
          response.status === 429
            ? text || "Zu viele Versuche. Bitte warte einige Minuten."
            : "Serverfehler bei der PIN-Prüfung.",
        );
        setMasterPinInput("");
      }
    } catch {
      setModalError("Netzwerkfehler bei der PIN-Prüfung.");
    } finally {
      setVerifyingPin(false);
    }
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoginError(null);

    if (!loginEmail.trim() || !loginPassword) {
      setLoginError("Bitte E-Mail-Adresse und Passwort eingeben.");
      return;
    }

    setLoginLoading(true);

    try {
      const response = await fetch(`${API_BASE}/api/admins_manager.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          email: loginEmail.trim(),
          password: loginPassword,
        }),
      });

      const rawData = await response.text();
      const data = JSON.parse(rawData);

      if (response.ok && data.success) {
        if (data.token) {
          localStorage.setItem("token", data.token);
        }
        setLoginEmail("");
        setLoginPassword("");

        // Weiterleitung zum Admin-Dashboard
        navigate("/admin_dashboard", { replace: true });
      } else {
        setLoginError(data.message || "Login fehlgeschlagen.");
      }
    } catch {
      setLoginError("Netzwerkfehler beim Login.");
    } finally {
      setLoginLoading(false);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setRegisterError(null);
    setRegisterSuccess(null);

    if (!registerEmail.trim() || !registerPassword) {
      setRegisterError("Bitte E-Mail-Adresse und Passwort eingeben.");
      return;
    }

    if (registerPassword.length < 8) {
      setRegisterError("Das Passwort muss mindestens 8 Zeichen lang sein.");
      return;
    }

    if (registerPassword !== registerPasswordConfirm) {
      setRegisterError("Die Passwörter stimmen nicht überein.");
      return;
    }

    setRegisterLoading(true);

    try {
      const response = await fetch(
        `${API_BASE}/api/admins_manager.php?action=register`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            action: "register",
            email: registerEmail.trim(),
            password: registerPassword,
          }),
        },
      );

      const rawData = await response.text();
      const data = JSON.parse(rawData);

      if (response.ok && data.success) {
        setRegisterSuccess(
          data.message ||
            "Admin erfolgreich registriert! Du kannst dich jetzt einloggen.",
        );
        setLoginEmail(registerEmail.trim());
        setRegisterEmail("");
        setRegisterPassword("");
        setRegisterPasswordConfirm("");
      } else {
        setRegisterError(data.message || "Registrierung fehlgeschlagen.");
      }
    } catch {
      setRegisterError("Netzwerkfehler bei der Registrierung.");
    } finally {
      setRegisterLoading(false);
    }
  };

  // Schritt 1: Master-PIN-Sperre
  if (!isUnlocked) {
    return (
      <div className="admin-register-container">
        <div className="admin-register-form">
          <h2>Zugang geschützt</h2>
          <h3>Admin-Bereich</h3>
          <p className="admin-auth-hint">
            Bitte gib die Master-PIN ein, um fortzufahren.
          </p>

          {modalError && <div className="error-message">{modalError}</div>}

          <form onSubmit={handleUnlock} noValidate>
            <div className="form-group">
              <label htmlFor="master-pin-input">Master-PIN</label>
              <input
                id="master-pin-input"
                name="master_pin"
                type="password"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                value={masterPinInput}
                onChange={(e) =>
                  setMasterPinInput(e.target.value.replace(/\D/g, ""))
                }
                autoFocus
                disabled={verifyingPin}
              />
            </div>
            <button type="submit" disabled={verifyingPin}>
              {verifyingPin ? "Prüfe PIN..." : "Entsperren"}
            </button>
          </form>
        </div>
      </div>
    );
  }

  // Schritt 2: Nach erfolgreicher Master-PIN -> Login oder Registrierung
  return (
    <div className="admin-register-container">
      <div className="admin-register-form">
        <div className="admin-auth-tabs">
          <button
            type="button"
            onClick={() => {
              setAuthMode("login");
              setLoginError(null);
            }}
            className={
              authMode === "login"
                ? "admin-auth-tabs__btn admin-auth-tabs__btn--active"
                : "admin-auth-tabs__btn"
            }
          >
            Login
          </button>
          <button
            type="button"
            onClick={() => {
              setAuthMode("register");
              setRegisterError(null);
              setRegisterSuccess(null);
            }}
            className={
              authMode === "register"
                ? "admin-auth-tabs__btn admin-auth-tabs__btn--active"
                : "admin-auth-tabs__btn"
            }
          >
            Registrierung
          </button>
        </div>

        {authMode === "login" ? (
          <form onSubmit={handleLogin} noValidate>
            <h2>Admin Login</h2>

            {loginError && <div className="error-message">{loginError}</div>}

            <div className="form-group">
              <label htmlFor="admin-login-email">E-Mail-Adresse</label>
              <input
                id="admin-login-email"
                type="email"
                autoComplete="email"
                value={loginEmail}
                onChange={(e) => setLoginEmail(e.target.value)}
                placeholder="admin@beispiel.de"
                disabled={loginLoading}
              />
            </div>

            <div className="form-group">
              <label htmlFor="admin-login-password">Passwort</label>
              <input
                id="admin-login-password"
                type="password"
                autoComplete="current-password"
                value={loginPassword}
                onChange={(e) => setLoginPassword(e.target.value)}
                placeholder="••••••••"
                disabled={loginLoading}
              />
            </div>

            <button type="submit" disabled={loginLoading}>
              {loginLoading ? "Melde an..." : "Einloggen"}
            </button>
          </form>
        ) : (
          <form onSubmit={handleRegister} noValidate>
            <h2>Admin Registrierung</h2>

            {registerError && (
              <div className="error-message">{registerError}</div>
            )}

            {registerSuccess && (
              <div className="success-message">{registerSuccess}</div>
            )}

            <div className="form-group">
              <label htmlFor="admin-register-email">E-Mail-Adresse</label>
              <input
                id="admin-register-email"
                type="email"
                autoComplete="email"
                value={registerEmail}
                onChange={(e) => setRegisterEmail(e.target.value)}
                placeholder="admin@beispiel.de"
                disabled={registerLoading}
              />
            </div>

            <div className="form-group">
              <label htmlFor="admin-register-password">Passwort</label>
              <input
                id="admin-register-password"
                type="password"
                autoComplete="new-password"
                value={registerPassword}
                onChange={(e) => setRegisterPassword(e.target.value)}
                placeholder="Min. 8 Zeichen"
                disabled={registerLoading}
              />
            </div>

            <div className="form-group">
              <label htmlFor="admin-register-password-confirm">
                Passwort wiederholen
              </label>
              <input
                id="admin-register-password-confirm"
                type="password"
                autoComplete="new-password"
                value={registerPasswordConfirm}
                onChange={(e) => setRegisterPasswordConfirm(e.target.value)}
                placeholder="Passwort bestätigen"
                disabled={registerLoading}
              />
            </div>

            <button type="submit" disabled={registerLoading}>
              {registerLoading ? "Registriere..." : "Admin registrieren"}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
