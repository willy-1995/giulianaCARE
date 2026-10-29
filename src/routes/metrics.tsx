import React, { useEffect, useState } from "react";
import "./Metrics.scss";

interface MetricsData {
  total_users: number;
  users_this_month: number;
  users_this_year: number;
  avg_subscription_days: number;
  packages_distribution: { price: string; count: number }[];
  total_clients: number;
  total_contacts: number;
  avg_contacts_per_client: number;
  postal_codes: { area_code: string; count: number }[];
  server_logs: string[];
}

export default function Metrics() {
  const [metrics, setMetrics] = useState<MetricsData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch("https://giuliana-care.de/api/metrics_api.php", {
      headers: {
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          setMetrics(data.metrics);
        } else {
          setError("Fehler beim Abrufen der Daten.");
        }
        setLoading(false);
      })
      .catch(() => {
        setError("Netzwerkfehler aufgetreten.");
        setLoading(false);
      });
  }, []);

  if (loading) return <div className="metrics-dashboard">Lade Metriken...</div>;
  if (error)
    return (
      <div className="metrics-dashboard" style={{ color: "red" }}>
        {error}
      </div>
    );
  if (!metrics) return null;

  return (
    <div className="metrics-dashboard">
      <h1>Admin Dashboard & Metriken</h1>

      {/* Kennzahlen Grid */}
      <div className="metrics-grid">
        <div className="metric-card">
          <p>Gesamtzahl User</p>
          <h2>{metrics.total_users}</h2>
        </div>
        <div className="metric-card">
          <p>User diesen Monat</p>
          <h2>{metrics.users_this_month}</h2>
        </div>
        <div className="metric-card">
          <p>User dieses Jahr</p>
          <h2>{metrics.users_this_year}</h2>
        </div>
        <div className="metric-card">
          <p>Ø Abo-Laufzeit</p>
          <h2>{metrics.avg_subscription_days} Tage</h2>
        </div>
        <div className="metric-card">
          <p>Gesamtzahl Clients</p>
          <h2>{metrics.total_clients}</h2>
        </div>
        <div className="metric-card">
          <p>Gesamtzahl Contacts</p>
          <h2>{metrics.total_contacts}</h2>
        </div>
        <div className="metric-card">
          <p>Ø Contacts pro Client</p>
          <h2>{metrics.avg_contacts_per_client}</h2>
        </div>
      </div>

      {/* Detail-Sektionen */}
      <div className="metrics-sections">
        {/* Pakete Verteilung */}
        <div>
          <h2>Gebuchte Pakete</h2>
          <ul>
            {metrics.packages_distribution.map((pkg, idx) => (
              <li key={idx}>
                <span>{pkg.price || "Unbekannt"}</span>
                <strong>{pkg.count} mal</strong>
              </li>
            ))}
          </ul>
        </div>

        {/* Postleitzahlen Verteilung */}
        <div>
          <h2>Postleitzahlen-Verteilung</h2>
          <div className="postal-list">
            {metrics.postal_codes.map((item, idx) => (
              <span key={idx}>
                <strong>{item.area_code}</strong>: {item.count}
              </span>
            ))}
          </div>
        </div>
      </div>

      {/* Server Logs & Exceptions */}
      <div className="logs-box-container">
        <h2>Server Logs & Exceptions</h2>
        <div className="logs-content">
          {metrics.server_logs.length > 0 ? (
            metrics.server_logs.map((log, idx) => <div key={idx}>{log}</div>)
          ) : (
            <div>Keine Logs vorhanden.</div>
          )}
        </div>
      </div>
    </div>
  );
}
