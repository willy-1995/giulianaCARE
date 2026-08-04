import React from "react";
import { useState, useEffect } from "react";
import {
  CountrySelect,
  StateSelect,
  CitySelect,
} from "react-country-state-city";
import "react-country-state-city/dist/react-country-state-city.css";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import {
  faVenusMars,
  faHeart,
  faSearch,
  faFingerprint,
  faArrowsUpDown,
  faGraduationCap,
  faBriefcase,
  faMapMarkerAlt,
  faCommentDots,
  faUsers,
  faQuoteLeft,
  faUsersBetweenLines,
} from "@fortawesome/free-solid-svg-icons";
import { useNavigate } from "react-router-dom";

interface UserDataFormProps {
  formData: any;
  setFormData: React.Dispatch<React.SetStateAction<any>>;
  onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
  message?: string;
  buttonLabel?: string;
}

function UserDataForm({
  formData,
  setFormData,
  onSubmit,
  message,
  buttonLabel = "Speichern",
}: UserDataFormProps) {
  const [countryId, setCountryId] = useState<number>(0);
  const [stateId, setStateId] = useState<number>(0);
  const [isLoaded, setIsLoaded] = useState(false);
  const [currentStep, setCurrentStep] = useState(0);
  const navigate = useNavigate();

  const steps = [
    { name: "Hub", icon: faUsersBetweenLines },
    { name: "Geschlecht", icon: faVenusMars },
    { name: "Sexualität", icon: faHeart },
    { name: "Suchmodus", icon: faSearch },
    { name: "Typ", icon: faFingerprint },
    { name: "Größe", icon: faArrowsUpDown },
    { name: "Bildung", icon: faGraduationCap },
    { name: "Job", icon: faBriefcase },
    { name: "Ort", icon: faMapMarkerAlt },
    { name: "Profiltext", icon: faCommentDots },
    { name: "Suche", icon: faUsers },
    { name: "Suchtext", icon: faQuoteLeft },
  ];

  useEffect(() => {
    const fetchUserData = async () => {
      const token = localStorage.getItem("token");
      try {
        const response = await fetch(
          "http://localhost/datingapp_ki/api/users_manager.php",
          {
            method: "GET",
            headers: {
              Authorization: `Bearer ${token}`,
            },
          },
        );
        const result = await response.json();
        if (result.success && result.data) {
          setFormData(result.data);
          // IDs explizit als Number parsen, um TS-Fehler in den Selects zu vermeiden
          if (result.data.country_id)
            setCountryId(parseInt(result.data.country_id));
          if (result.data.state_id) setStateId(parseInt(result.data.state_id));
        }
      } catch (error) {
        console.error("Fehler beim Laden der Daten:", error);
      } finally {
        setIsLoaded(true);
      }
    };

    fetchUserData();
  }, [setFormData]);

  const handleChange = (
    e: React.ChangeEvent<
      HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
    >,
  ) => {
    const { name, value } = e.target;
    setFormData((prev: any) => ({
      ...prev,
      [name]: value,
    }));
  };

  return (
    <div className="body-sub-div">
      <nav>
        {steps.map((step, index) => (
          <div
            key={index}
            className={`nav-item ${currentStep === index ? "active" : ""}`}
            onClick={() => setCurrentStep(index)}
            title={step.name}
          >
            <FontAwesomeIcon icon={step.icon} className="nav-icon" />
            <p className="nav-text">{step.name}</p>
          </div>
        ))}
      </nav>
      <form onSubmit={onSubmit}>
        {currentStep === 0 && (
          <fieldset>
            <h2>
              Welcher Community fühlst du dich zugehörig?
              <br />
              <span>Du kannst das später ändern!</span>
            </h2>
            <div className="radio-group">
              <label>
                Nicht spezifisch
                <input
                  type="radio"
                  name="hub"
                  value="all"
                  onChange={handleChange}
                  checked={formData.hub === "all"}
                />
              </label>
              <label>
                Neurodivers (ADHS, Autismus & Co)
                <input
                  type="radio"
                  name="hub"
                  value="neuro"
                  onChange={handleChange}
                  checked={formData.hub === "neuro"}
                />
              </label>

              <label>
                BDSM, Fetisch & Kink
                <input
                  type="radio"
                  name="hub"
                  value="kink"
                  onChange={handleChange}
                  checked={formData.hub === "kink"}
                />
              </label>
              <label>
                Fitness & Mental Health
                <input
                  type="radio"
                  name="hub"
                  value="fitness"
                  onChange={handleChange}
                  checked={formData.hub === "fitness"}
                />
              </label>
              <label>
                Cosplay, Anime & Nerds
                <input
                  type="radio"
                  name="hub"
                  value="cosplay"
                  onChange={handleChange}
                  checked={formData.hub === "cosplay"}
                />
              </label>
              <label>
                Tierfreunde
                <input
                  type="radio"
                  name="hub"
                  value="pets"
                  onChange={handleChange}
                  checked={formData.hub === "pets"}
                />
              </label>
              <label>
                Alternativ & Dark
                <input
                  type="radio"
                  name="hub"
                  value="dark"
                  onChange={handleChange}
                  checked={formData.hub === "dark"}
                />
              </label>
              <label>
                Aussteiger & Selbstversorger
                <input
                  type="radio"
                  name="hub"
                  value="dropout"
                  onChange={handleChange}
                  checked={formData.hub === "dropout"}
                />
              </label>
              <label>
                Reisende, Nomaden & Vanlife
                <input
                  type="radio"
                  name="hub"
                  value="travel"
                  onChange={handleChange}
                  checked={formData.hub === "travel"}
                />
              </label>
            </div>
          </fieldset>
        )}

        {currentStep === 1 && (
          <fieldset>
            <h2>Welchem Geschlecht gehörst du an?</h2>
            <div className="radio-group">
              <label>
                weiblich
                <input
                  type="radio"
                  name="gender"
                  value="female"
                  onChange={handleChange}
                  checked={formData.gender === "female"}
                />
              </label>
              <label>
                männlich
                <input
                  type="radio"
                  name="gender"
                  value="male"
                  onChange={handleChange}
                  checked={formData.gender === "male"}
                />
              </label>
              <label>
                andere
                <input
                  type="radio"
                  name="gender"
                  value="other"
                  onChange={handleChange}
                  checked={formData.gender === "other"}
                />
              </label>
            </div>
          </fieldset>
        )}

        {currentStep === 2 && (
          <fieldset>
            <h2>Welche Sexualität beschreibt dich am besten?</h2>
            <div className="radio-group">
              <label>
                Heterosexuell
                <input
                  type="radio"
                  name="sex"
                  value="hetero"
                  onChange={handleChange}
                  checked={formData.sex === "hetero"}
                />
              </label>
              <label>
                Homosexuell
                <input
                  type="radio"
                  name="sex"
                  value="homo"
                  onChange={handleChange}
                  checked={formData.sex === "homo"}
                />
              </label>
              <label>
                Bisexuell
                <input
                  type="radio"
                  name="sex"
                  value="bi"
                  onChange={handleChange}
                  checked={formData.sex === "bi"}
                />
              </label>
              <label>
                Andere
                <input
                  type="radio"
                  name="sex"
                  value="other"
                  onChange={handleChange}
                  checked={formData.sex === "other"}
                />
              </label>
            </div>
          </fieldset>
        )}

        {currentStep === 3 && (
          <fieldset>
            <h2>Was suchst Du?</h2>
            <div className="radio-group">
              <label>
                Dating
                <input
                  type="radio"
                  name="searchmode"
                  value="dating"
                  onChange={handleChange}
                  checked={formData.searchmode === "dating"}
                />
              </label>
              <label>
                Freundschaft
                <input
                  type="radio"
                  name="searchmode"
                  value="friendship"
                  onChange={handleChange}
                  checked={formData.searchmode === "friendship"}
                />
              </label>
            </div>
          </fieldset>
        )}

        {currentStep === 4 && (
          <fieldset>
            <h2>Welcher Typ bist du?</h2>
            <div className="radio-group">
              <label>
                asiatisch
                <input
                  type="radio"
                  name="ethnicity"
                  value="asian"
                  onChange={handleChange}
                  checked={formData.ethnicity === "asian"}
                />
              </label>
              <label>
                afrikanisch
                <input
                  type="radio"
                  name="ethnicity"
                  value="african"
                  onChange={handleChange}
                  checked={formData.ethnicity === "african"}
                />
              </label>
              <label>
                europäisch
                <input
                  type="radio"
                  name="ethnicity"
                  value="european"
                  onChange={handleChange}
                  checked={formData.ethnicity === "european"}
                />
              </label>
              <label>
                lateinamerikanisch
                <input
                  type="radio"
                  name="ethnicity"
                  value="latinamerican"
                  onChange={handleChange}
                  checked={formData.ethnicity === "latinamerican"}
                />
              </label>
            </div>
          </fieldset>
        )}

        {currentStep === 5 && (
          <fieldset>
            <h2>Wie groß bist du?</h2>
            <input
              type="number"
              name="height"
              value={formData.height || ""}
              onChange={handleChange}
              placeholder="Größe in Zentimeter (cm)"
            />
          </fieldset>
        )}

        {currentStep === 6 && (
          <fieldset>
            <h2>Was ist dein Bildungsgrad?</h2>
            <div className="radio-group">
              {[
                { val: "main-degree", label: "Hauptschule" },
                { val: "real-degree", label: "Realschule" },
                { val: "matura", label: "Abitur" },
                { val: "bachelor", label: "Bachelor/ Diplom" },
                { val: "master", label: "Master/ Diplom" },
                { val: "promotion", label: "Promotion" },
              ].map((item) => (
                <label key={item.val}>
                  {item.label}
                  <input
                    type="radio"
                    name="degree"
                    value={item.val}
                    onChange={handleChange}
                    checked={formData.degree === item.val}
                  />
                </label>
              ))}
            </div>
          </fieldset>
        )}

        {currentStep === 7 && (
          <fieldset>
            <h2>Wie steht's um deine Karriere?</h2>
            <input
              type="text"
              name="work"
              placeholder="z.B Selbständig als Tanzlehrerin..."
              value={formData.work || ""}
              onChange={handleChange}
            />
          </fieldset>
        )}

        {currentStep === 8 && isLoaded && (
          <fieldset>
            <h2>Wo wohnst Du?</h2>
            <CountrySelect
              className="library-input"
              onChange={(val: any) => {
                setCountryId(val.id);
                setFormData((prev: any) => ({
                  ...prev,
                  country_id: val.id,
                  country_name: val.name,
                  state_name: "",
                  city_name: "",
                }));
              }}
              placeHolder="Land"
              defaultValue={
                formData.country_name
                  ? ({ name: formData.country_name, id: countryId } as any)
                  : undefined
              }
            />
            <StateSelect
              className="library-input"
              countryid={countryId}
              onChange={(val: any) => {
                setStateId(val.id);
                setFormData((prev: any) => ({
                  ...prev,
                  state_id: val.id,
                  state_name: val.name,
                  city_name: "",
                }));
              }}
              placeHolder="Bundesland"
              defaultValue={
                formData.state_name
                  ? ({ name: formData.state_name, id: stateId } as any)
                  : undefined
              }
            />
            <CitySelect
              inputClassName="library-input"
              countryid={countryId}
              stateid={stateId}
              onChange={(val: any) => {
                setFormData((prev: any) => ({
                  ...prev,
                  city_name: val.name,
                }));
              }}
              placeHolder="Stadt"
              defaultValue={
                formData.city_name
                  ? ({ name: formData.city_name } as any)
                  : undefined
              }
            />

            <div className="radio-group">
              <h3>Suchradius</h3>
              {["30", "50", "100", "egal"].map((dist) => (
                <label key={dist}>
                  {dist === "egal" ? "egal" : `max. ${dist}km`}
                  <input
                    type="radio"
                    name="distance"
                    value={dist}
                    onChange={handleChange}
                    checked={String(formData.distance) === dist}
                  />
                </label>
              ))}
            </div>
          </fieldset>
        )}

        {currentStep === 9 && (
          <fieldset>
            <h2>Erzähle den Anderen etwas über dich!</h2>
            <textarea
              name="profiletext"
              value={formData.profiletext || ""}
              onChange={handleChange}
              placeholder="Über mich..."
              maxLength={400}
            />
            <p>{formData.profiletext?.length || 0} / 400 Zeichen</p>
          </fieldset>
        )}

        {currentStep === 10 && (
          <fieldset>
            <h2>Welches Geschlecht suchst Du?</h2>
            <div className="radio-group">
              {[
                { val: "female", label: "Frau" },
                { val: "male", label: "Mann" },
                { val: "divers", label: "Andere" },
                { val: "egal", label: "egal" },
              ].map((target) => (
                <label key={target.val}>
                  {target.label}
                  <input
                    type="radio"
                    name="target_gender"
                    value={target.val}
                    onChange={handleChange}
                    checked={formData.target_gender === target.val}
                  />
                </label>
              ))}
            </div>
          </fieldset>
        )}

        {currentStep === 11 && (
          <fieldset>
            <h2>Beschreibe, was für einen Menschen Du suchst</h2>
            <textarea
              name="searchtext"
              value={formData.searchtext || ""}
              maxLength={200}
              placeholder="Ich suche eine Person, die sich emotional öffnen kann und..."
              onChange={handleChange}
            />
            <p>{formData.searchtext?.length || 0} / 200 Zeichen</p>
          </fieldset>
        )}

        <div className="step-navigation-div">
          <div className="message-div">{message}</div>
          {currentStep > 0 && (
            <button
              type="button"
              onClick={() => setCurrentStep((prev) => prev - 1)}
            >
              Zurück
            </button>
          )}

          {currentStep < steps.length - 1 && (
            <button
              type="button"
              onClick={() => setCurrentStep((prev) => prev + 1)}
            >
              Weiter
            </button>
          )}
          <button type="submit">{buttonLabel}</button>
        </div>
      </form>
    </div>
  );
}

export default UserDataForm;
