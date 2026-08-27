import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import type { ApiResponse } from "../types/api";
import "./styles/main.scss";
//BASE URL
import { API_BASE } from "../assets/base_url";
import Navbar from "./components/navbar";
import Footer from "./components/footer";
import "./styles/registration.scss";

function Registration() {
  const navigate = useNavigate();
  const [message, setMessage] = useState("");

  // ===== STATES =====
  const [formData, setFormData] = useState({
    email: "",
    password: "",
    area_code: "",
    country: "",
    agbAccepted: false,
  });

  const handleChange = (
    e: React.ChangeEvent<
      HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
    >,
  ) => {
    const target = e.target;
    const { name, value } = target;

    const newValue =
      target instanceof HTMLInputElement && target.type === "checkbox"
        ? target.checked
        : value;

    setFormData((prev) => ({
      ...prev,
      [name]: newValue,
    }));
  };

  const isPasswordValid = formData.password.length >= 8;

  // ===== FUNCTIONS =====
  const registHandler = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    try {
      const response = await fetch(
        `${API_BASE}/api/users_manager.php`, // Deine API Route
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(formData),
        },
      );

      const result: ApiResponse = await response.json();

      if (result.success && result.token) {
        localStorage.setItem("token", result.token);
        setMessage(result.message);

        setTimeout(() => {
          // Navigation mit bestehendem State "fromRegistration"
          navigate("/dashboard", { state: { fromRegistration: true } });
        }, 2000);
      } else {
        setMessage(result.message);
      }
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Serverfehler");
    }
  };

  return (
    <div className="body-div registration-div">
      <Navbar />
      <div className="distance-div">
        <form onSubmit={registHandler} className="regist-form">
          <h1>Registrierung</h1>

          <select
            name="country"
            value={formData.country}
            onChange={handleChange}
            required
          >
            <option value="" disabled hidden>
              Land wählen
            </option>
            <option value="Deutschland">Deutschland</option>
            <option value="Österreich">Österreich</option>
            <option value="Schweiz">Schweiz</option>
          </select>

          <input
            type="text"
            name="area_code"
            placeholder="Postleitzahl"
            value={formData.area_code}
            onChange={handleChange}
            required
          />

          <input
            type="email"
            name="email"
            placeholder="E-Mail"
            value={formData.email}
            onChange={handleChange}
            required
          />

          <input
            type="password"
            name="password"
            placeholder="Passwort"
            value={formData.password}
            onChange={handleChange}
            required
          />

          {!isPasswordValid && formData.password.length > 0 && (
            <p className="error-text">Min. 8 Zeichen benötigt.</p>
          )}

          <label className="checkbox-label">
            <input
              type="checkbox"
              name="agbAccepted"
              checked={formData.agbAccepted}
              onChange={handleChange}
              required
            />
            Ich akzeptiere die{" "}
            <a href="/agb" target="_blank">
              AGB
            </a>{" "}
            und die{" "}
            <a href="/datenschutz" target="_blank">
              Datenschutzerklärung
            </a>
            .
          </label>

          <button disabled={!isPasswordValid}>Jetzt Registrieren</button>
        </form>

        {message && <div className="message-div">{message}</div>}
      </div>

      <Footer />
    </div>
  );
}

export default Registration;
