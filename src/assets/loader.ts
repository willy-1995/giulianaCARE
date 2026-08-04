export const loadClients = async (setLoading: (loading: boolean) => void) =>{
    const token = localStorage.getItem("token");
    if (!token) return { success: false, message: "Kein Token vorhanden" };
    setLoading(true);
    try{const response = await fetch(
      "http://localhost/giulianaCare/api/clients_manager.php",
      {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        }
      },
    );

    const rawdata = await response.text();
    console.log("Antwort: ", rawdata);
    const data = JSON.parse(rawdata);

   return data;
     
    } catch (error){
      console.log("Fehler beim Laden der Clients: ", error);
    } finally {
      setLoading(false);
    }
    
    
  };

//GET CONTACTS
  export const loadContacts = async (setLoading: (loading: boolean) => void) =>{
    const token = localStorage.getItem("token");
    if (!token) return { success: false, message: "Kein Token vorhanden" };
    setLoading(true);
    try{const response = await fetch(
      "http://localhost/giulianaCare/api/contacts_manager.php",
      {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        }
      },
    );

    const rawdata = await response.text();
    console.log("Antwort: ", rawdata);
    const data = JSON.parse(rawdata);

   return data;
     
    } catch (error){
      console.log("Fehler beim Laden der Kontakte: ", error);
    } finally {
      setLoading(false);
    }
    
  };

