//BASE URL
import { API_BASE } from "../assets/base_url";

//UPDATE CLIENT
export const updateClient = async (
  id: number,
  data: any,
  setLoading: (state: boolean) => void,
) => {
  setLoading(true);
  const token = localStorage.getItem("token");
  try {
    const response = await fetch(`${API_BASE}/api/clients_manager.php`, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ id, ...data }),
    });
    return await response.json();
  } catch (error) {
    console.error("Update Client Fehler:", error);
    return { success: false, message: "Netzwerkfehler beim Update" };
  } finally {
    setLoading(false);
  }
};

//UPDATE CONTACT
export const updateContact = async (
  id: number,
  data: any,
  setLoading: (state: boolean) => void,
) => {
  setLoading(true);
  const token = localStorage.getItem("token");

  if (!token) {
    setLoading(false);
    return { success: false, message: "Kein Token vorhanden" };
  }

  try {
    const response = await fetch(`${API_BASE}/api/contacts_manager.php`, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ id, ...data }),
    });

    return await response.json();
  } catch (error) {
    console.error("Update Contact Fehler:", error);
    return {
      success: false,
      message: "Netzwerkfehler beim Update des Kontakts",
    };
  } finally {
    setLoading(false);
  }
};

//TOGGLE STATUS
export const toggleStatus = async (
  clientId: number | string,
  status: string,
  setLoading?: (loading: boolean) => void,
) => {
  const token = localStorage.getItem("token");
  if (!token) return { success: false, message: "Kein Token vorhanden" };
  if (setLoading) setLoading(true);
  try {
    const response = await fetch(`${API_BASE}/api/clients_manager.php`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ id: clientId }),
    });

    const rawdata = await response.text();
    console.log("Antwort Toggle: ", rawdata);
    const data = JSON.parse(rawdata);

    return data;
  } catch (error) {
    console.log("Fehler beim Ändern des Status: ", error);
  } finally {
    if (setLoading) setLoading(false);
  }
};

// UPDATE USER
export const updateUser = async (
  data: {
    email?: string;
    price?: string;
    country?: string;
    area_code?: string;
  },
  setLoading: (state: boolean) => void,
) => {
  setLoading(true);
  const token = localStorage.getItem("token");
  if (!token) {
    setLoading(false);
    return { success: false, message: "Kein Token vorhanden" };
  }

  try {
    const response = await fetch(`${API_BASE}/api/users_manager.php`, {
      method: "PUT",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(data),
    });

    return await response.json();
  } catch (error) {
    console.error("Update User Fehler:", error);
    return {
      success: false,
      message: "Netzwerkfehler beim Update des Profils",
    };
  } finally {
    setLoading(false);
  }
};
