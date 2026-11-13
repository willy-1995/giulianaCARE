import React, { useState, useEffect } from "react";
import { API_BASE } from "../assets/base_url";

const COUNTRY_CODES = [
  { code: "+49", label: "🇩🇪 Deutschland (+49)" },
  { code: "+43", label: "🇦🇹 Österreich (+43)" },
  { code: "+41", label: "🇨🇭 Schweiz (+41)" },
];

const TestCallForm: React.FC = () => {
  const [name, setName] = useState("");
  const [medication, setMedication] = useState("");
  const [selectedCountry, setSelectedCountry] = useState("");
  const [phoneNumber, setPhoneNumber] = useState("");
  const [loading, setLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [isLocked, setIsLocked] = useState(false);
  const [timeLeft, setTimeLeft] = useState("");

  const LOCK_KEY = "twilio_test_call_lock_time";
  const LOCK_DURATION_MS = 30 * 60 * 1000;

  useEffect(() => {
    const checkLock = () => {
      const lockTime = localStorage.getItem(LOCK_KEY);
      if (lockTime) {
        const expirationTime = parseInt(lockTime, 10) + LOCK_DURATION_MS;
        const now = Date.now();

        if (now < expirationTime) {
          setIsLocked(true);
          const minutesLeft = Math.ceil((expirationTime - now) / 60000);
          setTimeLeft(`${minutesLeft} Min.`);
        } else {
          localStorage.removeItem(LOCK_KEY);
          setIsLocked(false);
        }
      }
    };

    checkLock();
    const interval = setInterval(checkLock, 60000);
    return () => clearInterval(interval);
  }, []);

  const handleCountryChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const newCode = e.target.value;
    setSelectedCountry(newCode);

    const currentDigitsOnly = phoneNumber.replace(/^\+\d+\s*/, "");
    setPhoneNumber(`${newCode} ${currentDigitsOnly}`);
  };

  const handleTestCall = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!phoneNumber || !name.trim() || !medication.trim()) return;

    setLoading(true);
    setStatusMessage("Anruf wird vorbereitet...");

    try {
      const bodyParams = new URLSearchParams({
        action: "test_call",
        phone_number: phoneNumber,
        name: name.trim(),
        medication: medication.trim(),
      });

      const response = await fetch(`${API_BASE}/api/call_manager.php`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: bodyParams.toString(),
      });

      const result = await response.json();

      if (result.success) {
        setStatusMessage("Dein Telefon klingelt gleich!");
      } else {
        setStatusMessage(`Fehler: ${result.message}`);
      }
    } catch (error) {
      console.error(error);
      setStatusMessage("Netzwerkfehler. Bitte versuche es später noch einmal.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      className="test-call-container"
      style={{ maxWidth: "400px", margin: "20px auto", textAlign: "center" }}
    >
      <h2>Probiere unsere Telefon-KI aus</h2>
      <p>Trage deine Daten ein und lass dich sofort anrufen.</p>

      <form
        onSubmit={handleTestCall}
        style={{ display: "flex", flexDirection: "column", gap: "10px" }}
      >
        <input
          type="text"
          placeholder="Dein Name (z. B. Herr Müller)"
          value={name}
          onChange={(e) => setName(e.target.value)}
          disabled={isLocked || loading}
          required
        />

        <input
          type="text"
          placeholder="Zusatzinfo / Medikation (z. B. 2 Gläser Wasser)"
          value={medication}
          onChange={(e) => setMedication(e.target.value)}
          disabled={isLocked || loading}
          required
        />

        <select
          value={selectedCountry}
          onChange={handleCountryChange}
          disabled={isLocked || loading}
          required
        >
          <option value="" disabled hidden>
            Bitte Ländervorwahl wählen...
          </option>
          {COUNTRY_CODES.map((country) => (
            <option key={country.code} value={country.code}>
              {country.label}
            </option>
          ))}
        </select>

        <input
          type="tel"
          placeholder="Telefonnummer"
          value={phoneNumber}
          onChange={(e) => setPhoneNumber(e.target.value)}
          disabled={isLocked || loading || !selectedCountry}
          required
        />

        <button
          type="submit"
          className="modal-button"
          disabled={
            isLocked ||
            loading ||
            !phoneNumber ||
            !selectedCountry ||
            !name.trim() ||
            !medication.trim()
          }
          style={{
            cursor:
              isLocked ||
              loading ||
              !selectedCountry ||
              !name.trim() ||
              !medication.trim()
                ? "not-allowed"
                : "pointer",
          }}
        >
          {loading && "Verbinde..."}
          {!loading && isLocked && `Gesperrt (noch ${timeLeft})`}
          {!loading && !isLocked && "Jetzt Testanruf starten"}
        </button>
      </form>

      {statusMessage && (
        <p style={{ marginTop: "15px", fontSize: "14px", color: "#555" }}>
          {statusMessage}
        </p>
      )}
    </div>
  );
};

export default TestCallForm;
