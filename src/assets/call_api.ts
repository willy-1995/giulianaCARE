import { loadClients } from "./loader";

interface ClientCalls {
  call_1: string | null;
  call_2: string | null;
  call_3: string | null;
  call_4: string | null;
}

let lastExecutedMinute: string | null = null;

export const checkCallTimes = async () => {
  const now = new Date();
  const currentMinute = now.toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });

  // 1. SOFORT abbrechen, wenn diese Minute bereits in Arbeit oder fertig ist
  if (lastExecutedMinute === currentMinute) return;
  
  // 2. SOFORT als ausgeführt markieren, bevor die asynchronen Aktionen (await) starten!
  lastExecutedMinute = currentMinute;

  console.log(`[${currentMinute}] Starte minütlichen Call- & Retry-Check...`);

  try {
    const result = await loadClients(() => {});

    if (result && result.success && Array.isArray(result.data)) {
      for (const client of result.data) {
        const callTimes = [client.call_1, client.call_2, client.call_3, client.call_4];
        
        for (const time of callTimes) {
          if (time && time.substring(0, 5) === currentMinute) {
            console.log(`[${currentMinute}] Triggering Call for Client ${client.id}`);
            // Tipp: Ohne 'await' abfeuern, wenn die Schleife nicht blockieren soll, 
            // oder mit 'await' lassen, falls dein Server die Last kontrolliert abarbeiten soll.
            await triggerCall(client.id); 
          }
        }
      }
    }
  } catch (error) {
    console.error("Fehler beim Laden oder Verarbeiten der Client-Daten:", error);
  }

  // Triggere den Retry-Handler in PHP (egal ob oben Clients fällig waren oder nicht)
  await triggerRetryHandler();
};

const triggerCall = async (clientId: string) => {
  try {
    await fetch("https://localhost/giulianaCARE/api/call_manager.php", {
      method: "POST",
      headers: { 
        "Content-Type": "application/x-www-form-urlencoded",
        // "X-Vapi-Secret": "DEIN_WEBHOOK_SECRET" // Falls du den Schutz im PHP eingebaut hast
      },
      body: `action=start&client_id=${clientId}`,
    });
  } catch (e) { 
    console.error(`Network Error beim Anruf-Trigger für Client ${clientId}`, e); 
  }
};

const triggerRetryHandler = async () => {
  try {
    await fetch("https://localhost/giulianaCARE/api/retry_handler.php", {
      method: "GET", 
    });
    console.log("Retry-Handler erfolgreich ausgeführt.");
  } catch (e) { 
    console.error("Retry-Handler Network Error", e); 
  }
};