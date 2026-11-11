import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";

export default function CarematchOwnProfile() {
  const [profile, setProfile] = useState<any>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    fetch("/carematch_api.php?action=profile", {
      headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          setProfile(data.user);
        } else {
          setError(data.message || "Fehler beim Laden des Profils.");
        }
      })
      .catch(() => setError("Verbindungsfehler."));
  }, []);

  if (error)
    return (
      <div>
        <p>{error}</p>
      </div>
    );
  if (!profile) return <div>Lade eigenes Profil...</div>;

  return (
    <div>
      <h2>Dein CareMatch Profil</h2>
      <div>
        <Link to="/carematch/dashboard">Zum Dashboard</Link> |{" "}
        <Link to="/carematch/profile/edit">Profil bearbeiten</Link>
      </div>
      <p>
        <strong>Rolle:</strong>{" "}
        {profile.role === "seeker" ? "Betreuungssuchender" : "Betreuungsperson"}
      </p>
      <p>
        <strong>Name:</strong> {profile.name}
      </p>
      <p>
        <strong>E-Mail:</strong> {profile.email}
      </p>
      <p>
        <strong>Geschlecht:</strong> {profile.gender || "Keine Angabe"}
      </p>
      <p>
        <strong>Region:</strong> {profile.region || "Keine Angabe"}
      </p>
      <p>
        <strong>Sprachen:</strong> {profile.languages || "Keine Angabe"}
      </p>
      <p>
        <strong>Pflegekasse Abrechnung:</strong>{" "}
        {profile.care_insurance_billing ? "Ja" : "Nein"}
      </p>
      <p>
        <strong>Services:</strong> {profile.services || "Keine Angabe"}
      </p>
      <p>
        <strong>Einschränkungen / Krankheiten:</strong>{" "}
        {profile.conditions || "Keine Angabe"}
      </p>
    </div>
  );
}
