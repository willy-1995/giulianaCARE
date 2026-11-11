import React, { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";

export default function CarematchEditProfile() {
  const [formData, setFormData] = useState({
    name: "",
    gender: "",
    region: "",
    languages: "",
    care_insurance_billing: 0,
    services: "",
    conditions: "",
  });
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const navigate = useNavigate();

  useEffect(() => {
    fetch("/carematch_api.php?action=profile", {
      headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.user) {
          setFormData({
            name: data.user.name || "",
            gender: data.user.gender || "",
            region: data.user.region || "",
            languages: data.user.languages || "",
            care_insurance_billing: data.user.care_insurance_billing ? 1 : 0,
            services: data.user.services || "",
            conditions: data.user.conditions || "",
          });
        }
      })
      .catch(() => setError("Fehler beim Laden der Profildaten."));
  }, []);

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

  const handleUpdate = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage("");
    setError("");
    try {
      const res = await fetch("/carematch_api.php", {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(formData),
      });
      const data = await res.json();
      if (data.success) {
        setMessage("Profil erfolgreich aktualisiert.");
        setTimeout(() => navigate("/carematch/profile"), 1500);
      } else {
        setError(data.message || "Aktualisierung fehlgeschlagen.");
      }
    } catch (err) {
      setError("Verbindungsfehler.");
    }
  };

  return (
    <div>
      <h2>Profil bearbeiten</h2>
      <div>
        <Link to="/carematch/profile">Zurück zum Profil</Link>
      </div>
      {message && <p>{message}</p>}
      {error && <p>{error}</p>}
      <form onSubmit={handleUpdate}>
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
          <label>Geschlecht</label>
          <select name="gender" value={formData.gender} onChange={handleChange}>
            <option value="">Bitte wählen</option>
            <option value="männlich">Männlich</option>
            <option value="weiblich">Weiblich</option>
            <option value="divers">Divers</option>
          </select>
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
        <button type="submit">Änderungen speichern</button>
      </form>
    </div>
  );
}
