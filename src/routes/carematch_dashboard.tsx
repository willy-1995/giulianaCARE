import React, { useState, useEffect } from "react";
import { useNavigate, Link } from "react-router-dom";

interface Profile {
  id: number;
  role: string;
  name: string;
  gender?: string;
  region?: string;
  languages?: string;
  care_insurance_billing?: number;
  services?: string;
  conditions?: string;
}

export default function CarematchDashboard() {
  const [filters, setFilters] = useState({
    region: "",
    gender: "",
    care_insurance_billing: "",
    languages: "",
    service: "",
    condition: "",
  });

  const [results, setResults] = useState<Profile[]>([]);
  const [offset, setOffset] = useState<number>(0);
  const [hasMore, setHasMore] = useState<boolean>(true);
  const navigate = useNavigate();

  const fetchResults = async (reset = false) => {
    const currentOffset = reset ? 0 : offset;
    const queryParams = new URLSearchParams({
      ...filters,
      action: "search",
      limit: String(10),
      offset: String(currentOffset),
    });

    try {
      const res = await fetch(`/carematch_api.php?${queryParams.toString()}`, {
        headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
      });
      const data = await res.json();
      if (data.success) {
        if (reset) {
          setResults(data.data);
          setOffset(10);
        } else {
          setResults((prev) => [...prev, ...data.data]);
          setOffset((prev) => prev + 10);
        }
        if (data.data.length < 10) {
          setHasMore(false);
        }
      }
    } catch (err) {
      console.error("Fehler bei der Suche", err);
    }
  };

  useEffect(() => {
    fetchResults(true);
  }, [filters]);

  const handleFilterChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
  ) => {
    const { name, value } = e.target;
    setFilters((prev) => ({ ...prev, [name]: value }));
    setOffset(0);
    setHasMore(true);
  };

  return (
    <div>
      <h2>CareMatch Dashboard & Suche</h2>
      <div>
        <Link to="/carematch/profile">Eigenes Profil ansehen</Link> |{" "}
        <Link to="/carematch/profile/edit">Profil bearbeiten</Link>
      </div>

      {/* Filter-Bereich */}
      <div>
        <div>
          <label>Region:</label>
          <input
            type="text"
            name="region"
            value={filters.region}
            onChange={handleFilterChange}
            placeholder="z.B. Berlin"
          />
        </div>
        <div>
          <label>Geschlecht:</label>
          <select
            name="gender"
            value={filters.gender}
            onChange={handleFilterChange}
          >
            <option value="">Alle</option>
            <option value="männlich">Männlich</option>
            <option value="weiblich">Weiblich</option>
            <option value="divers">Divers</option>
          </select>
        </div>
        <div>
          <label>Abrechnung Pflegekasse:</label>
          <select
            name="care_insurance_billing"
            value={filters.care_insurance_billing}
            onChange={handleFilterChange}
          >
            <option value="">Egal</option>
            <option value="1">Ja</option>
            <option value="0">Nein</option>
          </select>
        </div>
        <div>
          <label>Sprachen:</label>
          <input
            type="text"
            name="languages"
            value={filters.languages}
            onChange={handleFilterChange}
            placeholder="z.B. Deutsch"
          />
        </div>
        <div>
          <label>Service:</label>
          <input
            type="text"
            name="service"
            value={filters.service}
            onChange={handleFilterChange}
            placeholder="z.B. einkaufen"
          />
        </div>
        <div>
          <label>Einschränkungen / Krankheiten:</label>
          <input
            type="text"
            name="condition"
            value={filters.condition}
            onChange={handleFilterChange}
            placeholder="z.B. Rollstuhl"
          />
        </div>
      </div>

      {/* Ergebnisliste */}
      <div>
        <h3>Suchergebnisse</h3>
        {results.length === 0 ? (
          <p>Keine Einträge gefunden.</p>
        ) : (
          results.map((profile) => (
            <div key={profile.id}>
              <h4>{profile.name}</h4>
              <p>Region: {profile.region || "Keine Angabe"}</p>
              <p>Sprachen: {profile.languages || "Keine Angabe"}</p>
              <p>
                Pflegekasse: {profile.care_insurance_billing ? "Ja" : "Nein"}
              </p>
              <button
                onClick={() => navigate(`/carematch/profile/${profile.id}`)}
              >
                Profil ansehen
              </button>
            </div>
          ))
        )}
      </div>

      {hasMore && (
        <button onClick={() => fetchResults(false)}>
          Mehr laden (10 weitere)
        </button>
      )}
    </div>
  );
}
