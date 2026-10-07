import React, { useState } from "react";
import { useSearchParams, useNavigate, Link } from "react-router-dom";
import Navbar from "./components/navbar";
import Footer from "./components/footer";
import { API_BASE } from "../assets/base_url";
import "./styles/main.scss";
import "./styles/main.scss";

export function ResetPassword() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token");
  const navigate = useNavigate();

  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [message, setMessage] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (password !== confirmPassword) {
      setMessage("Die Passwörter stimmen nicht überein.");
      return;
    }

    if (!token) {
      setMessage("Ungültiger oder fehlender Token.");
      return;
    }

    setIsLoading(true);

    try {
      const response = await fetch(
        `${API_BASE}/api/auth/forgot_password.php?action=reset`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ token, password }),
        },
      );

      const data = await response.json();

      if (data.success) {
        setMessage(
          "Passwort erfolgreich geändert! Du wirst zum Login weitergeleitet...",
        );
        setTimeout(() => navigate("/login"), 3000);
      } else {
        setMessage(data.message || "Fehler beim Zurücksetzen.");
      }
    } catch (err) {
      setMessage("Serverfehler. Bitte versuche es später erneut.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="body-div login-div">
      <Navbar />
      <div className="distance-div">
        <form id="login-form" onSubmit={handleSubmit}>
          <h1>Neues Passwort setzen</h1>
          <input
            type="password"
            placeholder="Neues Passwort"
            minLength={8}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          <input
            type="password"
            placeholder="Passwort wiederholen"
            minLength={8}
            value={confirmPassword}
            onChange={(e) => setConfirmPassword(e.target.value)}
            required
          />
          <button type="submit" disabled={isLoading}>
            {isLoading ? "Speichere..." : "Passwort speichern"}
          </button>
          <Link to="/login" id="to-landing">
            <span className="link-normal">Zurück zum Login</span>
          </Link>
        </form>
        <div className="login-msg-div">{message}</div>
      </div>
      <Footer />
    </div>
  );
}

export default ResetPassword;
