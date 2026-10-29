import React, { useState } from "react";
//import "./AdminLogin.scss"; // Optional für Styles
import { API_BASE } from "../assets/base_url";

export default function AdminLogin() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [pin, setPin] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (pin.length !== 6) {
      setError("Die PIN muss genau 6 Stellen haben.");
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(`${API_BASE}/api/admin_manager.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password, pin }),
      });

      const data = await response.json();

      if (data.success) {
        localStorage.setItem("token", data.token);
        // Weiterleitung zum Metriken-Dashboard
        window.location.href = "/metrics";
      } else {
        setError(data.message || "Login fehlgeschlagen.");
      }
    } catch (err) {
      setError("Ein Netzwerkfehler ist aufgetreten.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="admin-login-container">
      <form onSubmit={handleLogin} className="admin-login-form">
        <h2>Admin Login</h2>

        {error && <div className="error-message">{error}</div>}

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
          <label>6-stellige PIN</label>
          <input
            type="text"
            maxLength={6}
            value={pin}
            onChange={(e) => setPin(e.target.value.replace(/\D/g, ""))} // Nur Zahlen erlauben
            required
            placeholder="123456"
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading ? "Wird eingeloggt..." : "Einloggen"}
        </button>
      </form>
    </div>
  );
}
