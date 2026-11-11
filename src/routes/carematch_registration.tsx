import React, { useState } from "react";
import { useNavigate, Link } from "react-router-dom";

export default function CarematchRegistration() {
  const [formData, setFormData] = useState({
    email: "",
    password: "",
    role: "seeker",
    name: "",
    gender: "",
    region: "",
    languages: "",
    care_insurance_billing: 0,
    services: "",
    conditions: "",
  });
  const [error, setError] = useState("");
  const navigate = useNavigate();

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
  ) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;
    setFormData({
      ...formData,
      [name]: type === "checkbox" ? (checked ? 1 : 0) : value,
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await fetch("api/carematch_api.php?action=register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(formData),
      });
      const data = await res.json();
      if (data.success) {
        localStorage.setItem("token", data.token);
        navigate("/carematch/dashboard");
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError("Verbindungsfehler.");
    }
  };

  return (
    <div>
      <h2>CareMatch Registrierung</h2>
      {error && <p>{error}</p>}
      <form onSubmit={handleSubmit}>
        <div>
          <label>Rolle</label>
          <select name="role" value={formData.role} onChange={handleChange}>
            <option value="seeker">Betreuungssuchender</option>
            <option value="caregiver">Betreuungsperson</option>
          </select>
        </div>
        <div>
          <label>Name</label>
          <input
            type="text"
            name="name"
            value={formData.name}
            onChange={handleChange}
            required
          />
        </div>
        <div>
          <label>E-Mail</label>
          <input
            type="email"
            name="email"
            value={formData.email}
            onChange={handleChange}
            required
          />
        </div>
        <div>
          <label>Passwort</label>
          <input
            type="password"
            name="password"
            value={formData.password}
            onChange={handleChange}
            required
          />
        </div>
        <div>
          <label>Region</label>
          <input
            type="text"
            name="region"
            value={formData.region}
            onChange={handleChange}
          />
        </div>
        <div>
          <label>Geschlecht</label>
          <select name="gender" value={formData.gender} onChange={handleChange}>
            <option value="">Bitte wählen</option>
            <option value="männlich">Männlich</option>
            <option value="weiblich">Weiblich</option>
            <option value="divers">Divers</option>
          </select>
        </div>
        <div>
          <label>Sprachen (kommasepariert)</label>
          <input
            type="text"
            name="languages"
            value={formData.languages}
            onChange={handleChange}
            placeholder="Deutsch, Englisch"
          />
        </div>
        <div>
          <label>
            <input
              type="checkbox"
              name="care_insurance_billing"
              checked={formData.care_insurance_billing === 1}
              onChange={handleChange}
            />
            Abrechnung mit Pflegekasse möglich
          </label>
        </div>
        <div>
          <label>Services (kommasepariert)</label>
          <input
            type="text"
            name="services"
            value={formData.services}
            onChange={handleChange}
            placeholder="einkaufen, putzen, botengänge"
          />
        </div>
        <div>
          <label>Einschränkungen / Krankheiten (kommasepariert)</label>
          <input
            type="text"
            name="conditions"
            value={formData.conditions}
            onChange={handleChange}
            placeholder="Rollstuhl, demenz"
          />
        </div>
        <button type="submit">Registrieren</button>
      </form>
      <p>
        Bereits registriert? <Link to="/carematch/login">Zum Login</Link>
      </p>
    </div>
  );
}
