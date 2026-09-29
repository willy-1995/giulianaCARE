import {
  useState,
  useEffect,
  useRef,
  type ChangeEvent,
  type FormEvent,
} from "react";
import Footer from "./components/footer";
import Navbar from "./components/navbar";
import "./styles/salespartnership.scss";
import "./styles/main.scss";
import { API_BASE } from "../assets/base_url";

interface AreaSuggestion {
  id: number;
  area_code: string;
}

// COUNTRY CODES für Telefonnummern
const PHONE_COUNTRY_CODES = [
  { code: "+49", label: "🇩🇪 Deutschland (+49)" },
  { code: "+43", label: "🇦🇹 Österreich (+43)" },
  { code: "+41", label: "🇨🇭 Schweiz (+41)" },
];

export function Salespartnership() {
  // --- Modal State ---
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false);

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

  // State für die gewählte Telefon-Ländervorwahl
  const [selectedPhoneCountry, setSelectedPhoneCountry] = useState<string>("");

  // Multiselect-Array für die gewählten PLZs / Vorwahlen
  const [selectedSalesAreas, setSelectedSalesAreas] = useState<string[]>([]);

  // --- States für die PLZ-Suche ---
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [suggestions, setSuggestions] = useState<AreaSuggestion[]>([]);
  const [isDropdownOpen, setIsDropdownOpen] = useState<boolean>(false);
  const searchContainerRef = useRef<HTMLLabelElement>(null);

  // --- Slideshow State & Daten ---
  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;

  const slideshowImages = [
    {
      src: getAssetUrl("media/flyer_provider.jpg"),
      alt: "Flyer verteilen",
      id: "img-flyer",
      caption: "Flyer verteilen",
    },
    {
      src: getAssetUrl("media/income_guy.jpg"),
      alt: "Einkommen aufbauen",
      id: "img-guy",
      caption: "Nebeneinkommen generieren",
    },
  ];

  const [currentImageIndex, setCurrentImageIndex] = useState<number>(0);

  // Timer für den automatischen Bildwechsel alle 6 Sekunden
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentImageIndex((prevIndex) =>
        prevIndex === slideshowImages.length - 1 ? 0 : prevIndex + 1,
      );
    }, 7000);

    return () => clearInterval(timer);
  }, [slideshowImages.length]);

  // Esc-Taste zum Schließen des Modals unterstützen
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape" && isModalOpen) {
        setIsModalOpen(false);
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [isModalOpen]);

  // Body-Scroll deaktivieren, wenn Modal offen ist
  useEffect(() => {
    if (isModalOpen) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "unset";
    }
    return () => {
      document.body.style.overflow = "unset";
    };
  }, [isModalOpen]);

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

  // Handler für die Auswahl des Telefon-Ländercodes
  const handlePhoneCountryChange = (e: ChangeEvent<HTMLSelectElement>) => {
    const newCode = e.target.value;
    setSelectedPhoneCountry(newCode);

    // Ersetzt eine bestehende Vorwahl (+...) am Anfang oder setzt die neue davor
    const currentDigitsOnly = formData.tel.replace(/^\+\d+\s*/, "");
    setFormData((prev) => ({
      ...prev,
      tel: `${newCode} ${currentDigitsOnly}`.trim(),
    }));
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
    setIsModalOpen(false);
  };

  return (
    <div className="body-div salespartner-div">
      <Navbar />
      <div className="salespartner-content">
        <div className="explain-container">
          <h2 className="sales-heading-1 sales-heading">
            Werde Vertriebspartner bei giulianaCARE
          </h2>
          <h3 className="sales-heading-2 sales-heading">
            und baue dir ein attraktives Nebeneinkommen auf!
          </h3>

          <ul>
            <h3>Wie es funktioniert</h3>

            <li>1. Registriere dich als Vertriebspartner</li>
            <li>2. Suche dir deine Vertriebsgebiete aus</li>
            <li>
              3. Nach einem kurzen Onboarding (online) erhältst du deine Flyer
            </li>
            <li>4. Verteile Flyer an geeigneten Stellen</li>
            <li>
              5. Verdiene für <b>jede Anmeldung</b> in deinem Vertriebsgebiet
              Geld und freue dich über eine <b>Umsatzbeteiligung</b>
            </li>
            {/* Button zum Öffnen des Modals */}
            <div className="modal-salesbutton-div">
              {" "}
              <button
                type="button"
                className="open-modal-btn"
                onClick={() => setIsModalOpen(true)}
              >
                Jetzt Vertriebspartner werden
              </button>
            </div>
          </ul>
        </div>

        {/* Slideshow mit einem gemeinsamen Container */}
        <div className="img-container">
          <div className="img-over-div">
            {/* TEXT-CONTAINER OBERHALB DES BILDES */}
            <div className="caption-container">
              {slideshowImages.map((img, index) => (
                <p
                  key={`caption-${img.id}`}
                  className={`slideshow-caption ${
                    index === currentImageIndex ? "active" : ""
                  }`}
                >
                  {img.caption}
                </p>
              ))}
            </div>

            {/* BILD-CONTAINER (UNVERÄNDERT) */}
            <div className="img-div">
              {slideshowImages.map((img, index) => (
                <img
                  key={img.id}
                  src={img.src}
                  alt={img.alt}
                  id={img.id}
                  className={`intro-img ${
                    index === currentImageIndex ? "active" : ""
                  }`}
                />
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* --- MODAL OVERLAY --- */}
      {isModalOpen && (
        <div className="modal-overlay" onClick={() => setIsModalOpen(false)}>
          <div
            className="modal"
            onClick={(e) => e.stopPropagation()} // Verhindert Schließen beim Klick ins Modal
          >
            <form onSubmit={handleSubmit} className="salespartner-form">
              <h3>1. Vertriebsgebiet wählen</h3>

              {/* Land auswählen */}
              <label>
                Land *
                <select
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
              </label>

              {/* PLZ / Vorwahl Suche */}
              <label className="search-container" ref={searchContainerRef}>
                PLZ / Vorwahl suchen *
                <input
                  type="text"
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
              </label>

              {/* Liste der ausgewählten Postleitzahlen */}
              {selectedSalesAreas.length > 0 && (
                <div className="selected-areas-container">
                  <h4>
                    Ausgewählte Postleitzahlen ({selectedSalesAreas.length}):
                  </h4>
                  <ul className="selected-areas-list">
                    {selectedSalesAreas.map((code) => (
                      <li key={code} className="selected-area-item">
                        <span>
                          PLZ / Bereich: <strong>{code}</strong>
                        </span>
                        <button
                          type="button"
                          className="remove-area-btn"
                          onClick={() => handleRemoveSalesArea(code)}
                          title="Entfernen"
                        >
                          Entfernen &times;
                        </button>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              <h3 className="second-h3">2. Persönliche Daten</h3>

              <label>
                Vorname *
                <input
                  type="text"
                  name="firstname"
                  value={formData.firstname}
                  onChange={handleInputChange}
                  required
                />
              </label>

              <label>
                Nachname *
                <input
                  type="text"
                  name="lastname"
                  value={formData.lastname}
                  onChange={handleInputChange}
                  required
                />
              </label>

              <label>
                Geburtsdatum *
                <input
                  type="date"
                  name="birthday"
                  value={formData.birthday}
                  onChange={handleInputChange}
                  required
                />
              </label>

              {/* Ländervorwahl für Telefonnummer */}
              <label>
                Ländervorwahl Telefon *
                <select
                  value={selectedPhoneCountry}
                  onChange={handlePhoneCountryChange}
                  required
                >
                  <option value="" disabled hidden>
                    Bitte Ländervorwahl wählen...
                  </option>
                  {PHONE_COUNTRY_CODES.map((country) => (
                    <option key={country.code} value={country.code}>
                      {country.label}
                    </option>
                  ))}
                </select>
              </label>

              {/* Telefonnummer Input */}
              <label>
                Telefonnummer *
                <input
                  type="tel"
                  name="tel"
                  value={formData.tel}
                  onChange={handleInputChange}
                  placeholder="+49 170 1234567"
                  disabled={!selectedPhoneCountry}
                  required
                />
              </label>

              <label>
                Straße & Hausnummer *
                <input
                  type="text"
                  name="street"
                  value={formData.street}
                  onChange={handleInputChange}
                  required
                />
              </label>

              <label>
                E-Mail Adresse *
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  required
                />
              </label>

              <label>
                Passwort *
                <input
                  type="password"
                  name="password"
                  value={formData.password}
                  onChange={handleInputChange}
                  required
                />
              </label>
              <div className="form-button-div">
                <button type="submit" className="submit-btn">
                  Als Vertriebspartner registrieren
                </button>
                <button
                  type="button"
                  className="modal-close-btn"
                  onClick={() => setIsModalOpen(false)}
                >
                  Abbrechen
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      <Footer />
    </div>
  );
}
