import { API_BASE } from "../assets/base_url";

export type AdminResource = "users" | "clients" | "contacts" | "protocol";

async function fetchAdminResource(
  resource: AdminResource,
  setLoading: (loading: boolean) => void,
  errorLabel: string,
) {
  const token = localStorage.getItem("token");
  if (!token) {
    return { success: false, message: "Kein Token vorhanden" };
  }

  setLoading(true);
  try {
    const response = await fetch(
      `${API_BASE}/api/admin_loader.php?resource=${encodeURIComponent(resource)}`,
      {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
      },
    );

    const rawData = await response.text();
    const data = JSON.parse(rawData);
    return data;
  } catch (error) {
    console.error(`${errorLabel}:`, error);
    return { success: false, message: errorLabel };
  } finally {
    setLoading(false);
  }
}

export const loadAllUsers = async (setLoading: (loading: boolean) => void) => {
  return fetchAdminResource(
    "users",
    setLoading,
    "Fehler beim Laden aller User",
  );
};

export const loadAllClients = async (
  setLoading: (loading: boolean) => void,
) => {
  return fetchAdminResource(
    "clients",
    setLoading,
    "Fehler beim Laden aller Clients",
  );
};

export const loadAllContacts = async (
  setLoading: (loading: boolean) => void,
) => {
  return fetchAdminResource(
    "contacts",
    setLoading,
    "Fehler beim Laden aller Kontakte",
  );
};

export const loadAllProtocol = async (
  setLoading: (loading: boolean) => void,
) => {
  return fetchAdminResource(
    "protocol",
    setLoading,
    "Fehler beim Laden aller Protokolle",
  );
};
