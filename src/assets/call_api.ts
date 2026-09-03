import { loadClients } from "./loader";

// Interface für die Struktur der Anrufzeiten
interface ClientCalls {
  call_1: string | null;
  call_2: string | null;
  call_3: string | null;
}

// Speicher für bereits ausgeführte Aktionen (Minute als Key)
// Damit verhindern wir, dass in der gleichen Minute mehrfach geloggt wird
let lastExecutedMinute: string | null = null;

export const checkCallTimes = async () => {
  const now = new Date();
  const currentMinute = now.toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });

  if (lastExecutedMinute === currentMinute) return;

  const result = await loadClients(() => {});

  if (result && result.success && Array.isArray(result.data)) {
    for (const client of result.data) {
      // Nur aktive Clients berücksichtigen
      if (client.status !== 'active') continue;

      // Zuordnung der Zeiten zu ihren Typen
      const callTimes = [
        { type: "call_1", time: client.call_1 },
        { type: "call_2", time: client.call_2 },
        { type: "call_3", time: client.call_3 }
      ];

      for (const call of callTimes) {
        if (call.time && call.time.substring(0, 5) === currentMinute) {
          console.log(`[${currentMinute}] Triggering ${call.type} for Client ${client.id}`);
          await triggerCall(client.id, call.type);
        }
      }
    }
  }

  // Triggere den Retry-Handler in PHP, um fällige 15-Minuten-Retrys zu prüfen
  await triggerRetryHandler();

  lastExecutedMinute = currentMinute;
};

const triggerCall = async (clientId: string | number, callType: string) => {
  try {
    await fetch("https://giuliana-care.de/api/call_manager.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=start&client_id=${clientId}&call_type=${callType}`,
    });
  } catch (e) {
    console.error("Network Error", e);
  }
};

const triggerRetryHandler = async () => {
  try {
    await fetch("https://giuliana-care.de/api/retry_handler.php", {
      method: "GET", // Oder POST, falls dein Script das erwartet
    });
    console.log("Retry-Handler triggered");
  } catch (e) {
    console.error("Retry-Handler Network Error", e);
  }
};