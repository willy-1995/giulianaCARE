//BASE URL
import { API_BASE } from "../assets/base_url";

export const deleteClient = async (id: number, setLoading: (loading: boolean) => void) =>{
    const token = localStorage.getItem("token");
    if (!token) return { success: false, message: "Kein Token vorhanden" };
    setLoading(true);
    try{const response = await fetch(
      `${API_BASE}/api/clients_manager.php`,
      {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({id: id}),
      },
    );

    const rawdata = await response.text();
    console.log("Antwort beim löschen: ", rawdata); //LOG
    const data = JSON.parse(rawdata);

   return data;
     
    } catch (error){
      console.log("Fehler beim Löschen: ", error); //LOG
        
    } finally {
      setLoading(false);
    }
    
  };

  //DELETE CONTACT
  export const deleteContact = async (
  id: number, 
  setLoading: (loading: boolean) => void
) => {
  const token = localStorage.getItem("token");
  setLoading(true);

  try {
    const response = await fetch(
      `${API_BASE}/api/contacts_manager.php`,
      {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        // NUR die ID des Kontakts schicken
        body: JSON.stringify({ id: id }),
      }
    );

    const data = await response.json();
    return data;
  } catch (error) {
    console.error("Fehler beim Löschen des Kontakts:", error);
    return { success: false };
  } finally {
    setLoading(false);
  }
};

// DELETE USER (Eigener Account)
export const deleteUser = async (
  setLoading: (loading: boolean) => void
) => {
  const token = localStorage.getItem("token");
  if (!token) return { success: false, message: "Kein Token vorhanden" };

  setLoading(true);

  try {
    const response = await fetch(`${API_BASE}/api/users_manager.php`, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
    });

    const data = await response.json();
    
    // Nach erfolgreichem Löschen das Token aus dem Client-Speicher entfernen
    if (data.success) {
      localStorage.removeItem("token");
    }

    return data;
  } catch (error) {
    console.error("Fehler beim Löschen des Accounts:", error);
    return { success: false, message: "Netzwerkfehler beim Löschen des Accounts" };
  } finally {
    setLoading(false);
  }
};