import { useEffect, useMemo, useRef, useState } from "react";
import "./styles/TEST_CLIENT_SEARCH.scss";

const API_URL = "https://localhost/giulianaCARE/api/client_manager.php";

// Passe diese Funktion an deinen tatsächlichen Speicherort des JWT-Tokens an.
function getAuthToken() {
  return localStorage.getItem("token");
}

// Vereinheitlicht Groß-/Kleinschreibung und deutsche Umlaute.
function normalize(value) {
  return String(value ?? "")
    .trim()
    .toLocaleLowerCase("de-DE");
}

type Client = {
  id: number;
  firstname: string;
  lastname: string;
  phone?: string;
  phone_number?: string;
  telephone?: string;
};

type ClientSearchProps = {
  onSelect?: (client: Client) => void;
};

export function ClientSearch({ onSelect }: ClientSearchProps) {
  const [clients, setClients] = useState([]);
  const [search, setSearch] = useState("");
  const [criterion, setCriterion] = useState("lastname");
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const containerRef = useRef(null);

  // Clients vom Backend laden
  useEffect(() => {
    const controller = new AbortController();

    async function loadClients() {
      try {
        setLoading(true);
        setError("");

        const token = getAuthToken();

        if (!token) {
          throw new Error("Du bist nicht angemeldet.");
        }

        const response = await fetch(API_URL, {
          method: "GET",
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
          },
          signal: controller.signal,
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(
            result.message || "Clients konnten nicht geladen werden.",
          );
        }

        setClients(Array.isArray(result.data) ? result.data : []);
      } catch (err) {
        if (err.name !== "AbortError") {
          setError(err.message || "Verbindungsfehler.");
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    }

    loadClients();

    return () => controller.abort();
  }, []);

  // Dropdown schließen, wenn außerhalb geklickt wird
  useEffect(() => {
    function handleOutsideClick(event) {
      if (
        containerRef.current &&
        !containerRef.current.contains(event.target)
      ) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);

    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
    };
  }, []);

  // Passende Clients anhand des ausgewählten Kriteriums filtern
  const filteredClients = useMemo(() => {
    const query = normalize(search);

    if (!query) return [];

    return clients
      .filter((client) => {
        let value = "";

        switch (criterion) {
          case "lastname":
            value = client.lastname;
            return normalize(value).startsWith(query);

          case "firstname":
            value = client.firstname;
            return normalize(value).startsWith(query);

          case "phone":
            value =
              client.phone ?? client.phone_number ?? client.telephone ?? "";

            // Telefonnummern auch ohne Leerzeichen, Bindestriche
            // oder Klammern vergleichen.
            return String(value)
              .replace(/\D/g, "")
              .includes(query.replace(/\D/g, ""));

          default:
            return false;
        }
      })
      .slice(0, 10);
  }, [clients, search, criterion]);

  function handleSelect(client) {
    setSearch(`${client.firstname ?? ""} ${client.lastname ?? ""}`.trim());
    setOpen(false);

    // Die aufrufende Komponente entscheidet, wie das Profil geöffnet wird.
    onSelect?.(client);
  }

  return (
    <div className="client-search" ref={containerRef}>
      <fieldset className="client-search__criteria">
        <legend>Suche nach</legend>

        <label>
          <input
            type="radio"
            name="clientSearchCriterion"
            value="lastname"
            checked={criterion === "lastname"}
            onChange={() => {
              setCriterion("lastname");
              setOpen(true);
            }}
          />
          Nachname
        </label>

        <label>
          <input
            type="radio"
            name="clientSearchCriterion"
            value="firstname"
            checked={criterion === "firstname"}
            onChange={() => {
              setCriterion("firstname");
              setOpen(true);
            }}
          />
          Vorname
        </label>

        <label>
          <input
            type="radio"
            name="clientSearchCriterion"
            value="phone"
            checked={criterion === "phone"}
            onChange={() => {
              setCriterion("phone");
              setOpen(true);
            }}
          />
          Telefonnummer
        </label>
      </fieldset>

      <div className="client-search__input-wrapper">
        <input
          type="search"
          className="client-search__input"
          placeholder={
            criterion === "lastname"
              ? "Nachnamen suchen ..."
              : criterion === "firstname"
                ? "Vornamen suchen ..."
                : "Telefonnummer suchen ..."
          }
          value={search}
          onChange={(event) => {
            setSearch(event.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          autoComplete="off"
          aria-label="Clients suchen"
          aria-expanded={open && search.trim().length > 0}
          aria-controls="client-search-results"
        />

        {open && search.trim().length > 0 && (
          <ul
            id="client-search-results"
            className="client-search__dropdown"
            role="listbox"
          >
            {loading ? (
              <li className="client-search__message">
                Clients werden geladen ...
              </li>
            ) : error ? (
              <li
                className="client-search__message client-search__error"
                role="alert"
              >
                {error}
              </li>
            ) : filteredClients.length > 0 ? (
              filteredClients.map((client) => (
                <li key={client.id}>
                  <button
                    type="button"
                    className="client-search__result"
                    onClick={() => handleSelect(client)}
                  >
                    <span className="client-search__name">
                      {client.firstname} {client.lastname}
                    </span>

                    <span className="client-search__details">
                      {client.phone ??
                        client.phone_number ??
                        client.telephone ??
                        ""}
                    </span>
                  </button>
                </li>
              ))
            ) : (
              <li className="client-search__message">
                Keine passenden Clients gefunden.
              </li>
            )}
          </ul>
        )}
      </div>
    </div>
  );
}
