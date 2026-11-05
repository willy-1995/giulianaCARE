import {
  useState,
  useEffect,
  useRef,
  useMemo,
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

// Hilfsfunktion zur Normalisierung (Groß-/Kleinschreibung & Umlaute)
function normalize(value: unknown) {
  return String(value ?? "")
    .trim()
    .toLocaleLowerCase("de-DE");
}

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

  // --- States für die PLZ-Suche (nach ClientSearch-Muster) ---
  const [areas, setAreas] = useState<AreaSuggestion[]>([]);
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [isDropdownOpen, setIsDropdownOpen] = useState<boolean>(false);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string>("");
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

  // Timer für den automatischen Bildwechsel alle 7 Sekunden
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

  // --- PLZ / Vertriebsgebiete vom Backend laden (sobald das Land gewählt ist) ---
  useEffect(() => {
    if (!formData.country) {
      setAreas([]);
      return;
    }

    const controller = new AbortController();

    async function loadAreas() {
      try {
        setLoading(true);
        setError("");

        const response = await fetch(
          `${API_BASE}/api/search_area.php?country=${encodeURIComponent(
            formData.country,
          )}`,
          {
            method: "GET",
            headers: {
              Accept: "application/json",
            },
            signal: controller.signal,
          },
        );

        const result = await response.json();

        if (!response.ok) {
          throw new Error(
            result.message || "Vertriebsgebiete konnten nicht geladen werden.",
          );
        }

        // Unterstützt sowohl direkte Arrays als auch verschachtelte Datenstrukturen
        const dataList = Array.isArray(result) ? result : result.data;
        setAreas(Array.isArray(dataList) ? dataList : []);
      } catch (err: unknown) {
        if ((err as Error).name !== "AbortError") {
          setError(
            (err as Error).message || "Verbindungsfehler bei der PLZ-Suche.",
          );
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    }

    loadAreas();

    return () => controller.abort();
  }, [formData.country]);

  // Gefilterte Vorschläge basierend auf der Eingabe (nach ClientSearch-Muster)
  const filteredAreas = useMemo(() => {
    const query = normalize(searchQuery);

    if (!query) return [];

    return areas
      .filter((item) => {
        const code = String(item.area_code ?? "");
        // Beginnt mit der Eingabe oder enthält sie
        return normalize(code).startsWith(query);
      })
      .slice(0, 10); // Max. 10 Vorschläge anzeigen
  }, [areas, searchQuery]);

  // Dropdown schließen, wenn außerhalb geklickt wird
  useEffect(() => {
    function handleOutsideClick(event: MouseEvent) {
      if (
        searchContainerRef.current &&
        !searchContainerRef.current.contains(event.target as Node)
      ) {
        setIsDropdownOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);
    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
    };
  }, []);

  // --- Handlers für Formularfelder ---
  const handleInputChange = (
    e: ChangeEvent<HTMLInputElement | HTMLSelectElement>,
  ) => {
    const { name, value } = e.target;

    setFormData((prev) => ({ ...prev, [name]: value }));

    // Wenn das Land gewechselt wird, Suche und gewählte Gebiete zurücksetzen
    if (name === "country") {
      setSearchQuery("");
      setAreas([]);
      setIsDropdownOpen(false);
      setSelectedSalesAreas([]);
    }
  };

  // Handler für die Auswahl des Telefon-Ländercodes
  const handlePhoneCountryChange = (e: ChangeEvent<HTMLSelectElement>) => {
    const newCode = e.target.value;
    setSelectedPhoneCountry(newCode);

    const currentDigitsOnly = formData.tel.replace(/^\+\d+\s*/, "");
    setFormData((prev) => ({
      ...prev,
      tel: `${newCode} ${currentDigitsOnly}`.trim(),
    }));
  };

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

            {/* BILD-CONTAINER */}
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
              {/* Land auswählen */}

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

              <label>
                Adresse (Straße, Nummer, PLZ, Stadt)
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
