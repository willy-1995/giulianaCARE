import React, { useState } from "react";
import { API_BASE } from "../assets/base_url";
import SubNavbar from "./components/navbar_sub";
import Footer from "./components/footer";
import "./styles/feedback.scss";

// Types / Interfaces
export interface FeedbackData {
  firstVisitUnderstoodService: number;
  howItWorksUnderstood: number;
  informationClear: number;
  dataSecurityClear: number;
  registrationEasy: number;
  clientSetupEasy: number;
  dashboardClear: number;
  settingsFoundQuickly: number;
  overallSatisfaction: number; // Neu hinzugefügt
  aiCallOnTime: string; // "ja" | "nein"
  aiFluentConversation: number;
  aiResponseSpeed: string;
  aiRetryAttemptsDone: string; // "ja" | "nein"
  aiEmergencyContactsCalled: string; // "ja" | "nein"
  comments: string;
}

interface FeedbackProps {
  onSubmitSuccess?: () => void;
}

export default function Feedback({ onSubmitSuccess }: FeedbackProps) {
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");

  // Initialer State
  const [formData, setFormData] = useState<FeedbackData>({
    firstVisitUnderstoodService: 0,
    howItWorksUnderstood: 0,
    informationClear: 0,
    dataSecurityClear: 0,
    registrationEasy: 0,
    clientSetupEasy: 0,
    dashboardClear: 0,
    settingsFoundQuickly: 0,
    overallSatisfaction: 0, // Neu
    aiCallOnTime: "",
    aiFluentConversation: 0,
    aiResponseSpeed: "",
    aiRetryAttemptsDone: "",
    aiEmergencyContactsCalled: "",
    comments: "",
  });

  // Handler für die Likert-Skala (1 bis 5)
  const handleScaleChange = (field: keyof FeedbackData, value: number) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  // Handler für Radio-Buttons (Ja/Nein/Geschwindigkeit) und Textarea
  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  // Absenden des Fragebogens
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setErrorMsg("");

    try {
      const token = localStorage.getItem("token");

      const response = await fetch(`${API_BASE}/api/feedback_manager.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...(token && { Authorization: `Bearer ${token}` }),
        },
        body: JSON.stringify(formData),
      });

      const result = await response.json();

      if (result.success) {
        setSubmitted(true);
        if (onSubmitSuccess) onSubmitSuccess();
      } else {
        setErrorMsg(result.message || "Fehler beim Speichern des Feedbacks.");
      }
    } catch (error) {
      console.error("Fehler beim Senden des Feedbacks:", error);
      setErrorMsg("Netzwerkfehler beim Senden des Feedbacks.");
    } finally {
      setLoading(false);
    }
  };

  // Hilfs-Komponente für Likert-Fragen (1 bis 5)
  const LikertQuestion = ({
    label,
    field,
  }: {
    label: string;
    field: keyof FeedbackData;
  }) => (
    <div className="question-block">
      <p className="question-label">{label}</p>
      <div className="scale-container">
        <span className="scale-legend">Stimme gar nicht zu</span>
        <div className="scale-options">
          {[1, 2, 3, 4, 5].map((val) => (
            <label key={val} className="scale-option">
              <input
                type="radio"
                name={field}
                value={val}
                checked={formData[field] === val}
                onChange={() => handleScaleChange(field, val)}
                required
              />
              <span className="custom-radio">{val}</span>
            </label>
          ))}
        </div>
        <span className="scale-legend">Stimme voll und ganz zu</span>
      </div>
    </div>
  );

  // Hilfs-Komponente für Ja / Nein Fragen
  const YesNoQuestion = ({
    label,
    field,
  }: {
    label: string;
    field: keyof FeedbackData;
  }) => (
    <div className="question-block">
      <p className="question-label">{label}</p>
      <div className="radio-group">
        {[
          { val: "ja", label: "Ja" },
          { val: "nein", label: "Nein" },
        ].map((opt) => (
          <label key={opt.val} className="radio-label">
            <input
              type="radio"
              name={field}
              value={opt.val}
              checked={formData[field] === opt.val}
              onChange={handleChange}
              required
            />
            <span>{opt.label}</span>
          </label>
        ))}
      </div>
    </div>
  );

  return (
    <div className="body-div feedback-page-div">
      <SubNavbar />

      <div className="feedback-container">
        {submitted ? (
          <div className="feedback-success">
            <h2>Vielen Dank für dein Feedback!</h2>
            <p>
              Deine Rückmeldung hilft uns sehr dabei, unseren Service und den
              KI-Assistenten kontinuierlich zu verbessern.
            </p>
          </div>
        ) : (
          <>
            <h2>User Experience Fragebogen</h2>
            <p className="feedback-intro">
              Bitte nimm dir kurz Zeit, um deine Erfahrungen mit unserer
              Plattform und dem KI-Assistenten zu bewerten.
            </p>

            {errorMsg && <div className="error-banner">{errorMsg}</div>}

            <form onSubmit={handleSubmit}>
              {/* ABSCHNITT 1: Website & Registrierung */}
              <fieldset className="form-section">
                <legend>Website & Registrierung</legend>

                <LikertQuestion
                  label="Ich habe beim ersten Besuch der Website verstanden, was die Dienstleistung ist."
                  field="firstVisitUnderstoodService"
                />

                <LikertQuestion
                  label="Ich habe die Funktionsweise des Services beim ersten Besuch der Website verstanden."
                  field="howItWorksUnderstood"
                />

                <LikertQuestion
                  label="Die Informationen auf der Website sind verständlich."
                  field="informationClear"
                />

                <LikertQuestion
                  label="Die Sicherheit meiner Daten ging aus den Informationen der Website hervor."
                  field="dataSecurityClear"
                />

                <LikertQuestion
                  label="Die Registrierung fiel mir leicht."
                  field="registrationEasy"
                />
              </fieldset>

              {/* ABSCHNITT 2: Nutzung & Dashboard */}
              <fieldset className="form-section">
                <legend>Einrichtung & Dashboard</legend>

                <LikertQuestion
                  label="Das Einrichten eines Klienten fiel mir leicht und alles war verständlich."
                  field="clientSetupEasy"
                />

                <LikertQuestion
                  label="Das Dashboard ist übersichtlich gestaltet."
                  field="dashboardClear"
                />

                <LikertQuestion
                  label="Ich habe die Einstellungen schnell gefunden."
                  field="settingsFoundQuickly"
                />
              </fieldset>

              {/* ABSCHNITT 3: KI Assistent */}
              <fieldset className="form-section">
                <legend>KI Assistent</legend>

                <YesNoQuestion
                  label="Der Anruf kam zu den angegebenen Zeiten."
                  field="aiCallOnTime"
                />

                <LikertQuestion
                  label="Die KI hat ein flüssiges Gespräch ermöglicht."
                  field="aiFluentConversation"
                />

                {/* Antwort-Geschwindigkeit */}
                <div className="question-block">
                  <p className="question-label">
                    Die KI hat in einer angemessenen Geschwindigkeit
                    geantwortet:
                  </p>
                  <div className="radio-group">
                    {[
                      { val: "ja", label: "Ja, genau richtig" },
                      { val: "nein", label: "Nein" },
                      { val: "zu_schnell", label: "Zu schnell" },
                      { val: "zu_langsam", label: "Zu langsam" },
                    ].map((opt) => (
                      <label key={opt.val} className="radio-label">
                        <input
                          type="radio"
                          name="aiResponseSpeed"
                          value={opt.val}
                          checked={formData.aiResponseSpeed === opt.val}
                          onChange={handleChange}
                          required
                        />
                        <span>{opt.label}</span>
                      </label>
                    ))}
                  </div>
                </div>

                <YesNoQuestion
                  label="Als die KI mich nicht erreicht hat, wurden 2 weitere Anruf-Versuche gestartet."
                  field="aiRetryAttemptsDone"
                />

                <YesNoQuestion
                  label="Als die KI mich nach allen Anrufversuchen nicht erreicht hat, wurden meine Notfallkontakte kontaktiert."
                  field="aiEmergencyContactsCalled"
                />

                <LikertQuestion
                  label="Ich bin zufrieden mit der Dienstleistung von giulianaCare Telefonbetreuung."
                  field="overallSatisfaction"
                />
              </fieldset>

              {/* ABSCHNITT 4: Freitext-Anmerkungen */}
              <fieldset className="form-section">
                <legend>Anmerkungen</legend>
                <div className="question-block">
                  <label htmlFor="comments" className="question-label">
                    Hast du weitere Anmerkungen oder Verbesserungsvorschläge?
                  </label>
                  <textarea
                    id="comments"
                    name="comments"
                    rows={4}
                    value={formData.comments}
                    onChange={handleChange}
                    placeholder="Deine Anmerkungen hier..."
                  />
                </div>
              </fieldset>

              <button type="submit" className="submit-btn" disabled={loading}>
                {loading ? "Wird gesendet..." : "Fragebogen absenden"}
              </button>
            </form>
          </>
        )}
      </div>

      <Footer />
    </div>
  );
}
