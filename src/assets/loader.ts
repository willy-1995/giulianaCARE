//BASE URL
import { API_BASE } from "../assets/base_url";

export const loadClients = async (setLoading: (loading: boolean) => void) =>{
    const token = localStorage.getItem("token");
    if (!token) return { success: false, message: "Kein Token vorhanden" };
    setLoading(true);
    try{const response = await fetch(
      `${API_BASE}/api/clients_manager.php`,
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
      `${API_BASE}/api/contacts_manager.php`,
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

//GET USER DATA
export const loadUser = async (setLoading: (loading: boolean) => void) => {
  const token = localStorage.getItem("token");
  if (!token) return { success: false, message: "Kein Token vorhanden" };
  setLoading(true);
  try {
    const response = await fetch(`${API_BASE}/api/users_manager.php`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
    });

    const rawdata = await response.text();
    console.log("User Antwort: ", rawdata);
    const data = JSON.parse(rawdata);

    return data;
  } catch (error) {
    console.log("Fehler beim Laden des Users: ", error);
  } finally {
    setLoading(false);
  }
};

//GET INCIDENTS PROTOCOL
export const loadProtocol = async (setLoading: (loading: boolean) => void) => {
  const token = localStorage.getItem("token");
  if (!token) return { success: false, message: "Kein Token vorhanden" };
  setLoading(true);
  try {
    const response = await fetch(`${API_BASE}/api/protocol_manager.php`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
    });

    const rawdata = await response.text();
    console.log("Protokoll Antwort: ", rawdata);
    const data = JSON.parse(rawdata);
    console.log("Protokoll geladen");

    return data;
  } catch (error) {
    console.log("Fehler beim Laden des Protokolls: ", error);
  } finally {
    setLoading(false);
  }
};

//VAPI STATS

export interface VapiCall {
  id: string;
  duration_seconds: number;
  duration_minutes: number;
  cost: number;
  created_at: string;
  status?: string;
  ended_reason?: string;
}

export interface VapiAverage {
  duration_seconds: number;
  duration_minutes: number;
  cost: number;
}

export interface VapiStatsResponse {
  success: boolean;
  totalMinutes: number;
  totalSeconds: number;
  totalCost: number;
  totalCallsCount: number;
  average: VapiAverage;
  calls: VapiCall[];
}

export const loadVapiStats = async (): Promise<VapiStatsResponse> => {
  try {
    const response = await fetch(`${API_BASE}/api/get_vapi_stats.php`);
    const data: VapiStatsResponse = await response.json();
    return data;
  } catch (error) {
    console.error("Fehler beim Laden der Vapi Stats:", error);
    return {
      success: false,
      totalMinutes: 0,
      totalSeconds: 0,
      totalCost: 0,
      totalCallsCount: 0,
      average: { duration_seconds: 0, duration_minutes: 0, cost: 0 },
      calls: [],
    };
  }
};