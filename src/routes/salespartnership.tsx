import {
  useState,
  useEffect,
  useRef,
  type ChangeEvent,
  type FormEvent,
} from "react";
import { useLocation } from "react-router-dom";
import Footer from "./components/footer";
import Navbar from "./components/navbar";
import "./styles/salespartnership.scss";
import "./styles/main.scss";
import { API_BASE } from "../assets/base_url";

interface AreaSuggestion {
  id: number;
  area_code: string;
}

export function Salespartnership() {
  // --- Formular State ---
  const [formData, setFormData] = useState({
    firstname: "",
    lastname: "",
    birthday: "",
    country: "",
    street: "",
    tel: "",
    email: "",
    password: "",
  });

  // Multiselect-Array für die gewählten PLZs / Vorwahlen
  const [selectedSalesAreas, setSelectedSalesAreas] = useState<string[]>([]);

  // --- States für die PLZ-Suche ---
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [suggestions, setSuggestions] = useState<AreaSuggestion[]>([]);
  const [isDropdownOpen, setIsDropdownOpen] = useState<boolean>(false);
  const searchContainerRef = useRef<HTMLDivElement>(null);

  // --- Handlers für Formularfelder ---
  const handleInputChange = (
    e: ChangeEvent<HTMLInputElement | HTMLSelectElement>,
  ) => {
    const { name, value } = e.target;

    setFormData((prev) => ({ ...prev, [name]: value }));

    // Wenn das Land geändert wird, Suche & Tags zurücksetzen
    if (name === "country") {
      setSearchQuery("");
      setSuggestions([]);
      setIsDropdownOpen(false);
      setSelectedSalesAreas([]);
    }
  };

  // --- PLZ-Suche per Fetch API (mit Debounce) ---
  useEffect(() => {
    if (!formData.country || searchQuery.trim() === "") {
      setSuggestions([]);
      setIsDropdownOpen(false);
      return;
    }

    const controller = new AbortController();

    const fetchSuggestions = async () => {
      try {
        const response = await fetch(
          `${API_BASE}/api/search_area.php?country=${encodeURIComponent(
            formData.country,
          )}&q=${encodeURIComponent(searchQuery)}`,
          { signal: controller.signal },
        );

        if (response.ok) {
          const data: AreaSuggestion[] = await response.json();
          setSuggestions(data);
          setIsDropdownOpen(data.length > 0);
        }
      } catch (err: unknown) {
        if ((err as Error).name !== "AbortError") {
          console.error("Fehler bei der PLZ-Suche:", err);
        }
      }
    };

    const timeoutId = setTimeout(fetchSuggestions, 150);

    return () => {
      clearTimeout(timeoutId);
      controller.abort();
    };
  }, [searchQuery, formData.country]);

  // Schließen des Such-Dropdowns bei Klick außerhalb
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        searchContainerRef.current &&
        !searchContainerRef.current.contains(event.target as Node)
      ) {
        setIsDropdownOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  // --- Hinzufügen / Entfernen von Vertriebsgebieten ---
  const handleAddSalesArea = (areaCode: string) => {
    if (!selectedSalesAreas.includes(areaCode)) {
      setSelectedSalesAreas((prev) => [...prev, areaCode]);
    }
    setSearchQuery("");
    setIsDropdownOpen(false);
  };

  const handleRemoveSalesArea = (areaCodeToRemove: string) => {
    setSelectedSalesAreas((prev) =>
      prev.filter((code) => code !== areaCodeToRemove),
    );
  };

  // --- Formular Absenden ---
  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();

    const payload = {
      ...formData,
      sales_areas: selectedSalesAreas,
    };

    console.log("Formular Payload:", payload);
    // Hier kannst du die Daten per fetch() an dein PHP-Backend senden
  };

  return (
    <div className="salespartner-div">
      <Navbar />
      <div className="salespartner-content">
        <h2>Werde Vertriebspartner bei giulianaCARE</h2>
        <h3>und baue dir ein attraktives Nebeneinkommen auf!</h3>

        <ul>
          <h3>Wie es funktioniert</h3>
          <li>1. Suche dir ein Vertriebsgebiet aus</li>
          <li>2. Registriere dich als Vertriebspartner</li>
          <li>
            3. Verteile Flyer oder sprich potentielle Kundinnen und Kunden
            direkt an
          </li>
          <li>
            4. Verdiene für jede Anmeldung in deinem Vertriebsgebiet Geld und
            Umsatzbeteiligung
          </li>
        </ul>

        <form onSubmit={handleSubmit} className="salespartner-form">
          <h3>1. Vertriebsgebiet wählen</h3>

          {/* Land auswählen */}
          <div className="form-group">
            <label htmlFor="country">Land *</label>
            <select
              id="country"
              name="country"
              value={formData.country}
              onChange={handleInputChange}
              required
            >
              <option value="">-- Bitte Land wählen --</option>
              <option value="Deutschland">Deutschland</option>
              <option value="Österreich">Österreich</option>
              <option value="Schweiz">Schweiz</option>
            </select>
          </div>

          {/* PLZ / Vorwahl Suche */}
          <div className="form-group search-container" ref={searchContainerRef}>
            <label htmlFor="area_search">PLZ / Vorwahl suchen *</label>
            <input
              type="text"
              id="area_search"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              disabled={!formData.country}
              placeholder={
                formData.country
                  ? "Tippen zum Suchen (z.B. 53...)"
                  : "Erst Land auswählen..."
              }
              autoComplete="off"
            />

            {/* Dropdown Vorschläge */}
            {isDropdownOpen && (
              <ul className="suggestions-dropdown">
                {suggestions.map((item) => (
                  <li
                    key={item.id}
                    onClick={() => handleAddSalesArea(item.area_code)}
                  >
                    {item.area_code}
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Gewählte Vertriebsgebiete (Badges) */}
          {selectedSalesAreas.length > 0 && (
            <div className="selected-areas">
              <label>Ausgewählte Gebiete (sales_areas):</label>
              <div className="badges-wrapper">
                {selectedSalesAreas.map((code) => (
                  <span key={code} className="area-badge">
                    {code}
                    <button
                      type="button"
                      onClick={() => handleRemoveSalesArea(code)}
                    >
                      &times;
                    </button>
                  </span>
                ))}
              </div>
            </div>
          )}

          <hr className="form-divider" />

          <h3>2. Persönliche Daten</h3>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="firstname">Vorname *</label>
              <input
                type="text"
                id="firstname"
                name="firstname"
                value={formData.firstname}
                onChange={handleInputChange}
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="lastname">Nachname *</label>
              <input
                type="text"
                id="lastname"
                name="lastname"
                value={formData.lastname}
                onChange={handleInputChange}
                required
              />
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="birthday">Geburtsdatum *</label>
              <input
                type="date"
                id="birthday"
                name="birthday"
                value={formData.birthday}
                onChange={handleInputChange}
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="tel">Telefonnummer *</label>
              <input
                type="tel"
                id="tel"
                name="tel"
                value={formData.tel}
                onChange={handleInputChange}
                placeholder="+49..."
                required
              />
            </div>
          </div>

          <div className="form-group">
            <label htmlFor="street">Straße & Hausnummer *</label>
            <input
              type="text"
              id="street"
              name="street"
              value={formData.street}
              onChange={handleInputChange}
              required
            />
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="email">E-Mail Adresse *</label>
              <input
                type="email"
                id="email"
                name="email"
                value={formData.email}
                onChange={handleInputChange}
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="password">Passwort *</label>
              <input
                type="password"
                id="password"
                name="password"
                value={formData.password}
                onChange={handleInputChange}
                required
              />
            </div>
          </div>

          <button type="submit" className="submit-btn">
            Als Vertriebspartner registrieren
          </button>
        </form>
      </div>
      <Footer />
    </div>
  );
}
