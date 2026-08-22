//BASE URL
import { API_BASE } from "../assets/base_url";

export const updateClient = async (
  id: number,
  data: any,
  setLoading: (state: boolean) => void,
) => {
  setLoading(true);
  const token = localStorage.getItem("token");
  try {
    const response = await fetch(`${API_BASE}/clients_manager.php`, {
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

export const updateContact = async (
  id: number,
  data: any,
  setLoading: (state: boolean) => void,
) => {
  setLoading(true);
  const token = localStorage.getItem("token");
  try {
    const response = await fetch(`${API_BASE}/contacts_manager.php`, {
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
    return { success: false, message: "Netzwerkfehler beim Update" };
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
    const response = await fetch(`${API_BASE}/clients_manager.php`, {
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
