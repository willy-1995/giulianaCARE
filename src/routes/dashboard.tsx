import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Link } from "react-router-dom";
import { useEffect } from "react";
import { loadClients } from "../assets/loader";
import { loadContacts } from "../assets/loader";
import { updateClient, updateContact } from "../assets/updater";
import { toggleStatus } from "../assets/updater";
import { userAge } from "../assets/age";
import { deleteClient } from "../assets/deleter";
import { deleteContact } from "../assets/deleter";
import { loadUser } from "../assets/loader";
import { loadProtocol } from "../assets/loader";
import { checkCallTimes } from "../assets/call_api";
import SubNavbar from "./components/navbar_sub";
import Footer from "./components/footer";
import "./styles/dashboard.scss";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faTrash, faSliders } from "@fortawesome/free-solid-svg-icons";
import { API_BASE } from "../assets/base_url";

interface Client {
  status: string;
  id: number;
  title: string;
  lastname: string;
  firstname: string;
  tel1: string;
  tel2: string;
  birthday: string;
  age: number;
  german_level: string;
  language: string;
  address: string;
  medication: string;
  info: string;
  call_1: string;
  call_2: string;
  call_3: string;
  medication_1: string;
  medication_2: string;
  medication_3: string;
}

interface Contact {
  id: number;
  client_id: number;
  lastname: string;
  firstname: string;
  tel1: string;
  tel2: string;
  email: string;
}

interface User {
  price: string;
}

interface Protocol {
  id: number;
  client_id: number;
  created_at: string;
  reason: string;
  lastname: string;
  firstname: string;
}

const initialUserState = {
  price: "",
};

const initialClientState = {
  status: "",
  title: "",
  firstname: "",
  lastname: "",
  tel1: "",
  tel2: "",
  birthday: "",
  language: "",
  german_level: "native",
  address: "",
  info: "",
  call_1: "",
  call_2: "",
  call_3: "",
  medication_1: "",
  medication_2: "",
  medication_3: "",
};

const initialContactState = {
  firstname: "",
  lastname: "",
  tel1: "",
  email: "",
};

const formatTime = (totalMinutes: number | undefined) => {
  if (totalMinutes === undefined || totalMinutes === null) return "--:--";
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")} Uhr`;
};

export default function Dashboard() {
  //STATES
  const [clientModal, setClientModal] = useState(false);
  const [contactModal, setContactModal] = useState(false);
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const [clients, setClients] = useState<Client[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [formDataClients, setFormDataClients] = useState(initialClientState);
  const [formDataContacts, setFormDataContacts] = useState(initialContactState);
  const [selectedClientId, setSelectedClientId] = useState<number | null>(null);
  const [isEditMode, setIsEditMode] = useState(false);
  const [currentEditId, setCurrentEditId] = useState<number | null>(null);
  const [user, setUser] = useState<User | null>(null); // for call display
  const [protocols, setProtocols] = useState<Protocol[]>([]);

  //=====================
  //GET LISTS
  //=====================

  //GET USER DATA
  useEffect(() => {
    // Beim Laden des Dashboards User-Daten abrufen
    loadUserHandler();
    // Falls du Clients und Kontakte auch hier lädst:
    // loadClients(setLoading);
    // loadContacts(setLoading);
  }, []);

  //Automatisches Neuladen beim Tab-Fokus (wenn in der DB etwas geändert wurde)
  useEffect(() => {
    const handleFocus = () => {
      loadUserHandler();
      loadClients(setLoading).then((res) => {
        if (res && res.success) setClients(res.data);
      });
      loadContacts(setLoading).then((res) => {
        if (res && res.success) setContacts(res.data);
      });
      loadProtocol(setLoading).then((res) => {
        if (res && res.success) setProtocols(res.data);
      });
    };

    window.addEventListener("focus", handleFocus);
    return () => window.removeEventListener("focus", handleFocus);
  }, []);

  //GET CLIENTS LIST
  useEffect(() => {
    const getClientData = async () => {
      const result = await loadClients(setLoading);
      if (result && result.success) {
        setClients(result.data);
      }
    };
    getClientData();
  }, []);

  //GET CONTACT LIST
  useEffect(() => {
    const getContactData = async () => {
      const result = await loadContacts(setLoading);
      if (result && result.success) {
        setContacts(result.data);
      }
    };
    getContactData();
  }, []);

  // GET PROTOCOL LIST
  useEffect(() => {
    const getProtocolData = async () => {
      const result = await loadProtocol(setLoading);
      if (result && result.success) {
        setProtocols(result.data);
      }
    };
    getProtocolData();
  }, []);

  //==================
  // CALL API
  //==================
  useEffect(() => {
    const timer = setInterval(() => {
      checkCallTimes();
    }, 30000);

    //Cleanup for reload or leaving site
    return () => clearInterval(timer);
  }, []);

  //==================
  //HANDLE MODALS
  //==================

  // CLIENT MODAL ÖFFNEN (für Neu ODER Bearbeiten)
  const openClientModal = (client?: Client) => {
    if (client && client.id) {
      setIsEditMode(true);
      setCurrentEditId(client.id);
      setFormDataClients({ ...client });
    } else {
      setIsEditMode(false);
      setCurrentEditId(null);
      setFormDataClients(initialClientState);
    }
    setClientModal(true);
  };

  // CONTACT MODAL ÖFFNEN (für Neu ODER Bearbeiten)
  const openContactModal = (clientId: number, contact?: Contact) => {
    setSelectedClientId(clientId);
    if (contact && contact.id) {
      setIsEditMode(true);
      setCurrentEditId(contact.id);
      setFormDataContacts({ ...contact });
    } else {
      setIsEditMode(false);
      setCurrentEditId(null);
      setFormDataContacts(initialContactState);
    }
    setContactModal(true);
  };

  //CLOSE MODALS
  const closeModals = () => {
    setClientModal(false);
    setContactModal(false);
    setIsEditMode(false);
    setCurrentEditId(null);
  };

  //HANDLE CHANGE (FOR FORM)
  // Change-Handler für Client-Formular
  const handleClientChange = (
    e: React.ChangeEvent<
      HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
    >,
  ) => {
    const { name, value } = e.target;
    setFormDataClients((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // Change-Handler für Contact-Formular
  const handleContactChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormDataContacts((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  //=====================================
  //HANDLE USER (GET/ LOAD DATA)
  //======================================
  //
  const loadUserHandler = async () => {
    const data = await loadUser(setLoading);
    if (data && data.success && data.user) {
      // Setzt das Preisschema des Benutzers
      setUser(data.user);
    }
  };

  //=====================================
  //HANDLE CLIENTS (ADD & UPDATE)
  //======================================

  const handleClientSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const token = localStorage.getItem("token");
    const cleanToken = token ? token.replace(/[\r\n]/g, "").trim() : "";

    console.log("LocalStorage 'token' Inhalt:", token);
    console.log("Gesendetes cleanToken:", cleanToken);

    //Convert times for MySQL
    const preparedData = {
      ...formDataClients,
      call_1: formDataClients.call_1 || null,
      call_2: formDataClients.call_2 || null,
      call_3: formDataClients.call_3 || null,
    };

    if (isEditMode && currentEditId) {
      // UPDATE LOGIC
      const result = await updateClient(
        currentEditId,
        preparedData,
        setLoading,
      );
      if (result && result.success) {
        setMessage("Klient erfolgreich aktualisiert!");
        setClientModal(false);
        const updatedData = await loadClients(setLoading);
        if (updatedData && updatedData.success) setClients(updatedData.data);
        setTimeout(() => setMessage(""), 3000);
      } else {
        setMessage("Fehler beim Aktualisieren.");
      }
    } else {
      // ADD LOGIC
      try {
        const response = await fetch(`${API_BASE}/api/clients_manager.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Access-Token": cleanToken,
          },
          body: JSON.stringify(preparedData),
        });

        const result = await response.json();

        if (result.success) {
          if (result.clientId) {
            setSelectedClientId(result.clientId);
          }
          setClientModal(false);
          setFormDataClients(initialClientState);
          setMessage("Person erfolgreich hinzugefügt!");
          setContactModal(true);
          const updatedData = await loadClients(setLoading);
          if (updatedData && updatedData.success) {
            setClients(updatedData.data);
          }
          setTimeout(() => setMessage(""), 3000);
        } else {
          setMessage("Hinzufügen fehlgeschlagen!");
          setTimeout(() => setMessage(""), 3000);
        }
      } catch (error) {
        console.error("Netzwerkfehler", error);
      }
    }
  };

  //=====================================
  //HANDLE CONTACTS (ADD & UPDATE)
  //======================================

  const handleContactSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const token = localStorage.getItem("token");
    const cleanToken = token ? token.replace(/[\r\n]/g, "").trim() : "";

    if (isEditMode && currentEditId) {
      // UPDATE LOGIC
      const result = await updateContact(
        currentEditId,
        formDataContacts,
        setLoading,
      );
      if (result && result.success) {
        setMessage("Kontakt erfolgreich aktualisiert!");
        setContactModal(false);
        const updatedData = await loadContacts(setLoading);
        if (updatedData && updatedData.success) setContacts(updatedData.data);
        setTimeout(() => setMessage(""), 3000);
      } else {
        setMessage("Fehler beim Aktualisieren.");
      }
    } else {
      // ADD LOGIC
      if (!selectedClientId) return;
      const payload = { ...formDataContacts, client_id: selectedClientId };
      try {
        const response = await fetch(`${API_BASE}/api/contacts_manager.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${cleanToken}`,
          },
          body: JSON.stringify(payload),
        });

        const result = await response.json();

        if (result.success) {
          setContactModal(false);
          setFormDataContacts(initialContactState);
          setSelectedClientId(null);
          setMessage("Kontakt erfolgreich hinzugefügt!");
          const updatedData = await loadContacts(setLoading);
          if (updatedData && updatedData.success) {
            setContacts(updatedData.data);
          }
          setTimeout(() => setMessage(""), 3000);
        } else {
          setMessage("Hinzufügen fehlgeschlagen!");
          setTimeout(() => setMessage(""), 3000);
        }
      } catch (error) {
        console.error("Netzwerkfehler", error);
      }
    }
  };

  // DELETE CLIENT
  const handleDeleteClient = async (id: number) => {
    if (!window.confirm("Möchten Sie diesen Eintrag wirklich löschen?")) return;
    const result = await deleteClient(id, setLoading);
    if (result && result.success) {
      setClients((prevClients) =>
        prevClients.filter((client) => client.id !== id),
      );
      setMessage("Eintrag wurde erfolgreich gelöscht.");
      setTimeout(() => setMessage(""), 3000);
    } else {
      setMessage(
        "Fehler beim Löschen: " + (result?.message || "Unbekannter Fehler"),
      );
    }
  };

  //DELETE CONTACT
  const handleDeleteContact = async (id: number) => {
    if (!window.confirm("Kontakt wirklich löschen?")) return;
    const result = await deleteContact(id, setLoading);
    if (result && result.success) {
      setContacts((prev) => prev.filter((c) => c.id !== id));
      setMessage("Kontakt entfernt.");
      setTimeout(() => setMessage(""), 2000);
    } else {
      setMessage("Fehler beim Löschen des Kontakts.");
    }
  };

  //========================================
  // HANDLE STATUS
  //========================================
  // STATUS TOGGLE MIT CONFIRM & OPTIMISTISCHEM UPDATE
  const handleToggleStatus = async (id: number, currentStatus: string) => {
    // Dynamische Frage basierend auf dem aktuellen Status definieren
    const confirmMessage =
      currentStatus === "active"
        ? "Anrufassistent deaktivieren?"
        : "Anrufassistent aktivieren?";

    // Bestätigungs-Dialog anzeigen
    const isConfirmed = window.confirm(confirmMessage);

    // Abbrechen, falls der Benutzer "Abbrechen" klickt
    if (!isConfirmed) return;

    const newStatus = currentStatus === "active" ? "inactive" : "active";

    // 1. UI sofort aktualisieren (für nahtlose Reaktion ohne Reload)
    setClients((prevClients) =>
      prevClients.map((client) =>
        client.id === id ? { ...client, status: newStatus } : client,
      ),
    );

    // 2. Im Hintergrund an das Backend senden
    const result = await toggleStatus(id, newStatus);

    // 3. Bei Fehler: Änderung wieder rückgängig machen
    if (!result || !result.success) {
      setClients((prevClients) =>
        prevClients.map((client) =>
          client.id === id ? { ...client, status: currentStatus } : client,
        ),
      );
      setMessage("Fehler beim Ändern des Status.");
      setTimeout(() => setMessage(""), 3000);
    }
  };

  return (
    <div className="body-div dashboard-div">
      <SubNavbar />

      {message && <div className="message-div">{message}</div>}
      <div className="dash-section">
        <div className="dash-header">
          <h2>Angemeldete Person</h2>
        </div>

        <div className="client-div section-sub-div">
          {loading ? (
            <div className="spinner">Daten werden geladen... </div>
          ) : (
            <div className="client-list">
              {clients.length > 0 ? (
                clients.map((client) => (
                  <div key={client.id} className="client-card">
                    <div className="card-sub client-area">
                      {/*
                      /////////////////////////////////////////////////////////////////
                      CLIENT CARD HEADING 
                      ////////////////////////////////////////////////////////////////*/}
                      <div className="client-heading">
                        <h2>
                          {client.title} {client.firstname} {client.lastname}{" "}
                          {/*
                          /////////TOGGLE SWITCH STATUS //////////////
                          */}
                          <button
                            type="button"
                            className={`status-toggle ${
                              client.status === "active"
                                ? "is-active"
                                : "is-inactive"
                            }`}
                            onClick={() =>
                              handleToggleStatus(client.id, client.status)
                            }
                            title="Klicken zum Umschalten"
                          >
                            <span className="toggle-slider"></span>
                            <span className="toggle-text">
                              {client.status === "active" ? "aktiv" : "inaktiv"}
                            </span>
                          </button>
                        </h2>

                        <div className="button-div">
                          <button
                            className="icon-button"
                            onClick={() => openClientModal(client)}
                          >
                            <FontAwesomeIcon icon={faSliders} />
                          </button>
                          <button
                            className="icon-button"
                            onClick={() => handleDeleteClient(client.id)}
                            aria-label={`${client.firstname} ${client.lastname} löschen`}
                          >
                            <FontAwesomeIcon icon={faTrash} />
                          </button>
                        </div>
                      </div>
                      <h3 id="data-grid-heading">Personendaten</h3>
                      {/*
                      /////////////////////////////////////////////////////////////////
                      CLIENT INFORMATION
                      ////////////////////////////////////////////////////////////////*/}
                      <div className="client-grid">
                        <p>
                          <b>Telefonnummer 1:</b> {client.tel1}
                        </p>
                        <p>
                          <b>Telefonnummer 2:</b> {client.tel2}
                        </p>
                        <p>
                          <b>Geburtsdatum:</b> {client.birthday} ({client.age})
                        </p>
                        <p>
                          <b>Adresse:</b> {client.address}
                        </p>
                      </div>
                      {/*
                      /////////////////////////////////////////////////////////////////
                      CLIENT INFORMATION
                      ////////////////////////////////////////////////////////////////*/}
                      <div className="client-calls">
                        <h3>Anruf(e)</h3>
                        <div className="calls">
                          <div className="calls-item">
                            <p>Anruf 1: {client.call_1}</p>
                            <p>Medikation/ Info: {client.medication_1}</p>
                          </div>
                          {/* ================= ANRUF 2 (Sichtbar bei "gutBetreut" und "rundumSorglos") ================= */}
                          {(user?.price === "gutBetreut" ||
                            user?.price === "rundumSorglos") && (
                            <div className="calls-item">
                              <p>Anruf 2: {client.call_2}</p>
                              <p>Medikation/ Info: {client.medication_2}</p>
                            </div>
                          )}
                          {/* ================= ANRUF 3 (Nur sichtbar bei "rundumSorglos") ================= */}
                          {user?.price === "rundumSorglos" && (
                            <div className="calls-item">
                              <p>Anruf 3: {client.call_3}</p>
                              <p>Medikation/ Info: {client.medication_3}</p>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                    {/*
                      /////////////////////////////////////////////////////////////////
                      CONTACT AREA
                      ////////////////////////////////////////////////////////////////*/}
                    <div className="contact-area">
                      <div className="contact-heading">
                        <h3>
                          Notfallkontakte:{" "}
                          <button onClick={() => openContactModal(client.id)}>
                            Hinzufügen
                          </button>
                        </h3>
                      </div>
                      <div className="contact-list">
                        {contacts
                          .filter((contact) => contact.client_id === client.id)
                          .map((contact) => (
                            <div key={contact.id} className="contact-card">
                              <div className="contact-card-grid">
                                <div className="card-item">
                                  <h3>Name</h3>
                                  <span>
                                    {contact.lastname}, {contact.firstname}
                                  </span>
                                </div>
                                <div className="card-item">
                                  <h3>Mobilnummer</h3>
                                  <span>{contact.tel1}</span>
                                </div>
                                <div className="card-item">
                                  <h3>E-Mail</h3>
                                  <span>{contact.email}</span>
                                </div>
                              </div>
                              <div className="button-div">
                                <button
                                  className="icon-button"
                                  onClick={() =>
                                    openContactModal(client.id, contact)
                                  }
                                >
                                  <FontAwesomeIcon icon={faSliders} />
                                </button>
                                <button
                                  className="icon-button"
                                  onClick={() =>
                                    handleDeleteContact(contact.id)
                                  }
                                >
                                  <FontAwesomeIcon icon={faTrash} />
                                </button>
                              </div>
                            </div>
                          ))}

                        {contacts.filter((c) => c.client_id === client.id)
                          .length === 0 && (
                          <p>Keine Notfallkontakte hinterlegt.</p>
                        )}
                      </div>
                    </div>
                    {/*==============================
                    PROTOCOL AREA 
                    =================================
                    */}
                    <div className="protocol-area">
                      <h3>Anruf-Protokoll</h3>
                      <div className="protocol-list">
                        {protocols
                          .filter(
                            (protocol) => protocol.client_id === client.id,
                          )
                          .map((protocol) => (
                            <div key={protocol.id} className="protocol-card">
                              <p>
                                <strong>Datum/Uhrzeit:</strong>{" "}
                                {new Date(protocol.created_at).toLocaleString(
                                  "de-DE",
                                )}
                              </p>
                              <p>
                                <strong>Grund/Ergebnis:</strong>{" "}
                                {protocol.reason}
                              </p>
                            </div>
                          ))}

                        {protocols.filter(
                          (protocol) => protocol.client_id === client.id,
                        ).length === 0 && (
                          <p>Keine Protokolleinträge vorhanden.</p>
                        )}
                      </div>
                    </div>
                  </div>
                ))
              ) : (
                <div className="noListInfo">
                  <p>Trage deine zu betreuende Person ein</p>
                  <button onClick={() => openClientModal()}>
                    Person hinzufügen
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {clientModal && (
        <div className="modal-overlay">
          <div className="modal">
            <form onSubmit={handleClientSubmit}>
              <div className="form-div">
                <h3> {isEditMode ? "Person bearbeiten" : "Neue Person"}</h3>

                <label>
                  Anrede
                  <select
                    name="title"
                    value={formDataClients.title || ""}
                    onChange={handleClientChange}
                    required
                  >
                    <option value="" disabled hidden>
                      Bitte wählen...
                    </option>
                    <option value="Herr">Herr</option>
                    <option value="Frau">Frau</option>
                    <option value="Divers">Divers</option>
                  </select>
                </label>
                <label>
                  Nachname
                  <input
                    type="text"
                    name="lastname"
                    placeholder="Bspw. Müller"
                    value={formDataClients.lastname}
                    onChange={handleClientChange}
                    required
                  />
                </label>
                <label>
                  Vorname
                  <input
                    type="text"
                    name="firstname"
                    placeholder="Bspw. Manfred"
                    value={formDataClients.firstname}
                    onChange={handleClientChange}
                    required
                  />
                </label>
                <label>
                  Telefonnummer 1
                  <input
                    type="tel"
                    name="tel1"
                    placeholder="Bspw. 0228 12345678"
                    value={formDataClients.tel1}
                    onChange={handleClientChange}
                    required
                  />
                </label>
                <label>
                  Telefonnummer 2
                  <input
                    type="tel"
                    name="tel2"
                    placeholder="Bspw. 0228 12345678"
                    value={formDataClients.tel2}
                    onChange={handleClientChange}
                  />
                </label>
                <label>
                  Geburtsdatum
                  <input
                    type="date"
                    name="birthday"
                    value={formDataClients.birthday}
                    onChange={handleClientChange}
                  />
                </label>
                <label>
                  Sprachniveau in Deutsch
                  <select
                    name="german_level"
                    value={formDataClients.german_level}
                    onChange={handleClientChange}
                  >
                    <option value="native">Muttersprache</option>
                    <option value="conversation_good">
                      Kommunikation gut möglich
                    </option>
                    <option value="conversation_bad">
                      Kommunikation schwer möglich
                    </option>
                    <option value="no_german">Spricht kein Deutsch</option>
                  </select>
                </label>
                <label>
                  Bevorzugte Sprache
                  <input
                    name="language"
                    type="text"
                    placeholder="z.B. Russisch, Polnisch, Türkisch..."
                    value={formDataClients.language}
                    onChange={handleClientChange}
                  />
                </label>
                <label>
                  Adresse (Straße, Hausnummer, Postleitzahl, Ort)
                  <input
                    type="text"
                    name="address"
                    placeholder="Beispielstraße 1, 12345 Musterstadt"
                    value={formDataClients.address}
                    onChange={handleClientChange}
                  />
                </label>

                <label>
                  Zusatzinformation
                  <input
                    type="text"
                    name="info"
                    placeholder="z.B. Klient ist Dement..."
                    value={formDataClients.info}
                    onChange={handleClientChange}
                  />
                </label>

                <h3 id="call-change-heading">Wähle die Anrufzeiten</h3>
                <br />
                <p>Aktuelles Paket: {user?.price}</p>

                {/* ================= ANRUF 1 (Für alle Tarife sichtbar) ================= */}
                <label htmlFor="call_1">Anruf 1 um:</label>
                <input
                  type="time"
                  id="call_1"
                  name="call_1"
                  required
                  value={formDataClients.call_1 || ""}
                  onChange={handleClientChange}
                />

                <label htmlFor="medication_1">
                  Medikation / Info Anruf 1:
                  <textarea
                    id="medication_1"
                    name="medication_1"
                    value={formDataClients.medication_1 || ""}
                    onChange={handleClientChange}
                  ></textarea>
                </label>

                {/* ================= ANRUF 2 (Sichtbar bei "gutBetreut" und "rundumSorglos") ================= */}
                {(user?.price === "gutBetreut" ||
                  user?.price === "rundumSorglos") && (
                  <>
                    <label htmlFor="call_2">
                      Anruf 2 um:
                      <input
                        type="time"
                        id="call_2"
                        name="call_2"
                        value={formDataClients.call_2 || ""}
                        onChange={handleClientChange}
                      />
                    </label>

                    <label htmlFor="medication_2">
                      Medikation / Info Anruf 2:
                      <textarea
                        id="medication_2"
                        name="medication_2"
                        value={formDataClients.medication_2 || ""}
                        onChange={handleClientChange}
                      ></textarea>
                    </label>
                  </>
                )}

                {/* ================= ANRUF 3 (Nur sichtbar bei "rundumSorglos") ================= */}
                {user?.price === "rundumSorglos" && (
                  <>
                    <label htmlFor="call_3">
                      Anruf 3 um:
                      <input
                        type="time"
                        id="call_3"
                        name="call_3"
                        value={formDataClients.call_3 || ""}
                        onChange={handleClientChange}
                      />
                    </label>

                    <label htmlFor="medication_3">
                      Medikation / Info Anruf 3:
                      <textarea
                        id="medication_3"
                        name="medication_3"
                        value={formDataClients.medication_3 || ""}
                        onChange={handleClientChange}
                      ></textarea>
                    </label>
                  </>
                )}
              </div>
              <button type="submit">Speichern</button>
              <button type="button" onClick={closeModals}>
                Abbrechen
              </button>
            </form>
          </div>
        </div>
      )}

      {contactModal && (
        <div className="modal-overlay">
          <div className="modal">
            <form onSubmit={handleContactSubmit}>
              <div className="form-div">
                <fieldset>
                  <legend>
                    {isEditMode ? "Kontakt bearbeiten" : "Neuer Notfallkontakt"}
                  </legend>
                  <label>
                    Nachname
                    <input
                      type="text"
                      name="lastname"
                      placeholder="Bspw. Müller"
                      value={formDataContacts.lastname}
                      onChange={handleContactChange}
                    />
                  </label>
                  <label>
                    Vorname
                    <input
                      type="text"
                      name="firstname"
                      placeholder="Bspw. Manfred"
                      value={formDataContacts.firstname}
                      onChange={handleContactChange}
                    />
                  </label>
                  <label>
                    Telefon
                    <input
                      type="tel"
                      name="tel1"
                      placeholder="Bspw. 0228 12345678"
                      value={formDataContacts.tel1}
                      onChange={handleContactChange}
                    />
                  </label>
                  <label>
                    E-Mail-Adresse
                    <input
                      type="email"
                      name="email"
                      placeholder="beispiel@muster.de..."
                      value={formDataContacts.email}
                      onChange={handleContactChange}
                    />
                  </label>
                </fieldset>
              </div>
              <button type="submit">Speichern</button>
              <button type="button" onClick={closeModals}>
                Abbrechen
              </button>
            </form>
          </div>
        </div>
      )}
      <Footer />
    </div>
  );
}
