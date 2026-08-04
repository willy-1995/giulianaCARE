import React, { useState } from "react";

interface InitialMessageModalProps {
  partnerId: number;
  partnerName: string;
  token: string | null;
  onClose: () => void;
  onSuccess: (convId: number) => void;
}

export default function InitialMessageModal({
  partnerId,
  partnerName,
  token,
  onClose,
  onSuccess,
}: InitialMessageModalProps) {
  const [text, setText] = useState("");
  const [sending, setSending] = useState(false);

  const handleSend = async () => {
    if (!text.trim()) return;
    setSending(true);

    try {
      // Wir senden an einen speziellen Endpunkt,
      // der die Konversation erstellt UND die Nachricht speichert.
      const response = await fetch(
        "http://localhost/datingapp_ki/api/conversation.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({
            receiver_id: partnerId,
            message_text: text,
          }),
        },
      );

      const data = await response.json();
      if (data.success) {
        onSuccess(data.conversation_id); // Leitet dich zum Messenger weiter
        onClose();
      }
    } catch (error) {
      console.error("Senden fehlgeschlagen", error);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="modal-overlay">
      <div className="modal-content">
        <h3>Nachricht an {partnerName}</h3>
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder="Schreib etwas Nettes..."
        />
        <div className="modal-buttons">
          <button onClick={onClose}>Abbrechen</button>
          <button onClick={handleSend} disabled={sending}>
            {sending ? "Sendet..." : "Senden"}
          </button>
        </div>
      </div>
    </div>
  );
}
