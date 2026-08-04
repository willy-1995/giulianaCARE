const API_BASE = "http://localhost/giulianaCare/api";

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
