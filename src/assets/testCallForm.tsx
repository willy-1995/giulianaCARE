import React, { useState, useEffect } from "react";
//BASE URL
import { API_BASE } from "../assets/base_url";

const TestCallForm: React.FC = () => {
  const [phoneNumber, setPhoneNumber] = useState("");
  const [loading, setLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [isLocked, setIsLocked] = useState(false);
  const [timeLeft, setTimeLeft] = useState("");

  // Key für den LocalStorage
  const LOCK_KEY = "vapi_test_call_lock_time";
  const LOCK_DURATION_MS = 30 * 60 * 1000; // 30 Minuten in Millisekunden

  useEffect(() => {
    // Prüfen, ob eine Sperre im Browser aktiv ist
    const checkLock = () => {
      const lockTime = localStorage.getItem(LOCK_KEY);
      if (lockTime) {
        const expirationTime = parseInt(lockTime, 10) + LOCK_DURATION_MS;
        const now = Date.now();

        if (now < expirationTime) {
          setIsLocked(true);
          // Countdown-Text berechnen
          const minutesLeft = Math.ceil((expirationTime - now) / 60000);
          setTimeLeft(`${minutesLeft} Min.`);
        } else {
          // Sperre ist abgelaufen
          localStorage.removeItem(LOCK_KEY);
          setIsLocked(false);
        }
      }
    };

    checkLock();
    // Jede Minute den Status aktualisieren (für die Restzeitanzeige)
    const interval = setInterval(checkLock, 60000);
    return () => clearInterval(interval);
  }, []);

  const handleTestCall = async (e: React.FormEvent) => {
    console.log("Testanruf geklickt"); //////////
    e.preventDefault();
    if (!phoneNumber) return;

    setLoading(true);
    setStatusMessage("Anruf wird vorbereitet...");

    try {
      // Absenden an deine call_manager.php auf dem Server (bzw. Localhost)
      const response = await fetch(`${API_BASE}/api/call_manager.php`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        // Wir senden eine neue Action "test_call" und die Nummer direkt mit
        body: `action=test_call&phone_number=${encodeURIComponent(phoneNumber)}`,
      });

      console.log("Fetch geschickt!");
      const result = await response.json();

      if (result.success) {
        console.log("Anruf gestartet");
        /*
         setStatusMessage(
          "Dein Telefon klingelt gleich! Der Button ist nun für 30 Min. gesperrt.",
        );
        */

        // 30-Minuten-Sperre aktivieren & im LocalStorage sichern
        /*
         const now = Date.now();
        localStorage.setItem(LOCK_KEY, now.toString());
        setIsLocked(true);
        setTimeLeft("30 Min.");
        */
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
      <h3>Probiere unsere Telefon-KI aus</h3>
      <p>Trage deine Nummer ein und lass dich sofort anrufen.</p>

      <form onSubmit={handleTestCall}>
        <input
          type="tel"
          placeholder="+49 170 1234567"
          value={phoneNumber}
          onChange={(e) => setPhoneNumber(e.target.value)}
          disabled={isLocked || loading}
          required
        />

        <button
          type="submit"
          disabled={isLocked || loading || !phoneNumber}
          style={{
            cursor: isLocked || loading ? "not-allowed" : "pointer",
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
